<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Middleware;

use Horde\Core\Auth\Jwt\JwtService;
use Horde\Core\Session\HordeSession;
use Horde\SessionHandler\Exception\SessionException;
use Horde\SessionHandler\SessionHandler;
use Horde\SessionHandler\SessionId;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Modern PSR-15 JWT-to-HordeSession loader.
 *
 * Resolves a {@see HordeSession} from any of two JWT transports:
 *
 * 1. **`Authorization: Bearer <access-jwt>`** — verified as an access
 *    token. The session id comes from the access token's `refresh_jti`
 *    claim, which points at the session row the paired refresh token
 *    keys. Used by cross-domain SPAs, mobile clients, and any consumer
 *    that cannot rely on cookies.
 *
 * 2. **`horde_jwt_refresh` cookie** — verified as a refresh token. The
 *    session id IS the refresh token's `jti` claim (per the JWT-JTI
 *    architecture decision). Used by browser flows that landed at
 *    Horde-rendered login.
 *
 * Bearer wins when both are present, on the principle that the explicit
 * header beats the passive cookie. Logs at info level so client bugs
 * that send both inadvertently surface in logs.
 *
 * Both paths use {@see SessionHandler::load()} directly. Neither path
 * calls `session_id()` and neither depends on PHP's session module
 * running afterwards. Suited to modern PSR-15 routes that own their
 * session lifecycle through {@see \Horde\Core\Session\SessionLifecycle}
 * plus middleware, not through the legacy `Horde_Session::setup()` flow.
 *
 * The JWT is verified before the lookup in both paths. An unverified
 * jti must not be used to load a session row even though forging a
 * valid JWT is computationally infeasible: verification catches expired
 * tokens and rejected signatures cheaply.
 *
 * Never short-circuits. Routes that need to demand authentication
 * compose this with {@see DemandAuthenticatedUser} or similar
 * downstream middleware. Failure modes (no transport, invalid token,
 * verification failure, no matching session row) all leave the request
 * attribute unset and let the inner handler run normally; the
 * downstream {@see HordeSessionMiddleware} then minds the `Horde`
 * session-id cookie path.
 */
class JwtSessionLoader implements MiddlewareInterface
{
    /** Request attribute carrying the loaded {@see HordeSession}. */
    public const ATTRIBUTE_SESSION = 'session';

    /** Cookie name for the JWT refresh token. */
    public const COOKIE_NAME = 'horde_jwt_refresh';

    /** Request header carrying the JWT access token (Bearer scheme). */
    public const AUTHORIZATION_HEADER = 'Authorization';

    /** Bearer scheme prefix in the Authorization header (case-insensitive match). */
    private const BEARER_PREFIX = 'Bearer ';

    public function __construct(
        private readonly ?JwtService $jwtService,
        private readonly SessionHandler $sessionHandler,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $session = $this->resolveSession($request);
        if ($session !== null) {
            $request = $request->withAttribute(self::ATTRIBUTE_SESSION, $session);
        }
        return $handler->handle($request);
    }

    /**
     * Resolve a {@see HordeSession} from any JWT transport on the request.
     *
     * Tries `Authorization: Bearer` first, then the `horde_jwt_refresh`
     * cookie. Returns null and logs the reason for any failure. The
     * middleware never raises; the worst case is that the inner handler
     * runs without a session attribute and downstream code treats the
     * request as unauthenticated.
     */
    private function resolveSession(ServerRequestInterface $request): ?HordeSession
    {
        if ($this->jwtService === null) {
            // JWT is not configured for this install. Nothing to verify.
            // Downstream middleware (e.g. HordeSessionMiddleware) will
            // mint a fresh session via the cookie path.
            return null;
        }

        $bearerToken = $this->extractBearerToken($request);
        $cookieToken = $this->extractCookieToken($request);

        if ($bearerToken !== null && $cookieToken !== null) {
            // Both transports present. Use Bearer (explicit header beats
            // passive cookie) but log so client misconfigurations are
            // visible in operator logs.
            $this->logger->info(
                'JwtSessionLoader: both Authorization: Bearer header and '
                . self::COOKIE_NAME . ' cookie are present; using Bearer'
            );
        }

        if ($bearerToken !== null) {
            $session = $this->loadFromBearer($bearerToken);
            if ($session !== null) {
                return $session;
            }
            // Bearer failed verification or session lookup. Do NOT fall
            // through to the cookie: if the caller sent a Bearer, they
            // were explicit about which transport to use. Falling through
            // could log a user in via a stale cookie when the Bearer
            // intentionally pointed at a different session.
            return null;
        }

        if ($cookieToken !== null) {
            return $this->loadFromCookie($cookieToken);
        }

        return null;
    }

    /**
     * Extract the Bearer token from the Authorization header.
     *
     * Returns null when no Authorization header is present or the scheme
     * is not Bearer (e.g. Basic auth). Empty Bearer token strings (just
     * `Bearer ` with no value) are treated as absent.
     */
    private function extractBearerToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine(self::AUTHORIZATION_HEADER);
        if ($header === '') {
            return null;
        }
        if (stripos($header, self::BEARER_PREFIX) !== 0) {
            // Non-Bearer Authorization (Basic, Digest, etc.). Not our concern.
            return null;
        }
        $token = trim(substr($header, strlen(self::BEARER_PREFIX)));
        return $token !== '' ? $token : null;
    }

    /**
     * Extract the refresh-token JWT from the cookie.
     */
    private function extractCookieToken(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        $jwt = $cookies[self::COOKIE_NAME] ?? null;
        return is_string($jwt) && $jwt !== '' ? $jwt : null;
    }

    /**
     * Verify an access token and load the session row via the
     * `refresh_jti` claim.
     */
    private function loadFromBearer(string $token): ?HordeSession
    {
        try {
            $verified = $this->jwtService->verifyAccessToken($token);
        } catch (InvalidArgumentException $e) {
            $this->logger->debug('JWT access token verification failed', [
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        $refreshJti = $verified->getClaim('refresh_jti');
        if (!is_string($refreshJti) || $refreshJti === '') {
            $this->logger->debug('JWT access token has no refresh_jti claim');
            return null;
        }

        return $this->loadSessionRow($refreshJti, 'bearer');
    }

    /**
     * Verify a refresh token and load the session row via the `jti`
     * claim.
     */
    private function loadFromCookie(string $token): ?HordeSession
    {
        try {
            $verified = $this->jwtService->verifyRefreshToken($token);
        } catch (InvalidArgumentException $e) {
            $this->logger->debug('JWT refresh token verification failed', [
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        $jti = $verified->getClaim('jti');
        if (!is_string($jti) || $jti === '') {
            $this->logger->debug('JWT refresh token has no jti claim');
            return null;
        }

        return $this->loadSessionRow($jti, 'cookie');
    }

    /**
     * Load a session row by id. Shared tail of both transports.
     */
    private function loadSessionRow(string $sessionId, string $transport): ?HordeSession
    {
        try {
            $session = $this->sessionHandler->load(new SessionId($sessionId));
        } catch (SessionException $e) {
            // Backend storage error. Same outcome as a missing row:
            // unauthenticated.
            $this->logger->warning('JWT session lookup failed', [
                'sid' => $sessionId,
                'transport' => $transport,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        if ($session === null) {
            // Token is valid (signature OK, not expired) but the session
            // row is gone. Logout destroyed it; or it was pruned by GC.
            // Token is effectively dead.
            $this->logger->info('JWT session row not found', [
                'sid' => $sessionId,
                'transport' => $transport,
            ]);
            return null;
        }

        if (!$session instanceof HordeSession) {
            // SessionHandler is configured with a non-HordeSession
            // factory. Defensive check; should not happen in production.
            $this->logger->warning('JWT session row is not a HordeSession', [
                'sid' => $sessionId,
                'transport' => $transport,
                'class' => get_debug_type($session),
            ]);
            return null;
        }

        return $session;
    }
}
