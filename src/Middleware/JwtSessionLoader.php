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
 * Reads the `horde_jwt_refresh` cookie, verifies the JWT, and loads
 * the matching {@see HordeSession} via {@see SessionHandler::load()}
 * keyed by the refresh token's `jti` (which IS the session id, per
 * the JWT-JTI architecture decision). The loaded session is set as
 * a request attribute under {@see ATTRIBUTE_SESSION}.
 *
 * Differs from the legacy {@see JwtSession} middleware in two ways:
 *
 * - Uses {@see SessionHandler::load()} directly. Does NOT call
 *   `session_id()` and does NOT depend on PHP's session module
 *   running afterwards. Suited to modern PSR-15 routes that own
 *   their session lifecycle through {@see \Horde\Core\Session\SessionLifecycle}
 *   plus middleware, not through the legacy
 *   `Horde_Session::setup()` flow.
 * - Verifies the JWT before lookup. An unverified JTI must not be
 *   used to load a session row even though forging a valid JWT is
 *   computationally infeasible: verification catches expired
 *   refresh tokens and rejected signatures cheaply.
 *
 * Never short-circuits. Routes that need to demand authentication
 * compose this with {@see DemandAuthenticatedUser} or similar
 * downstream middleware. Failure modes (no cookie, invalid cookie,
 * verification failure, no matching session row) all leave the
 * request attribute unset and let the inner handler run normally.
 */
class JwtSessionLoader implements MiddlewareInterface
{
    /** Request attribute carrying the loaded {@see HordeSession}. */
    public const ATTRIBUTE_SESSION = 'session';

    /** Cookie name for the JWT refresh token. */
    public const COOKIE_NAME = 'horde_jwt_refresh';

    public function __construct(
        private readonly JwtService $jwtService,
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
     * Resolve a {@see HordeSession} from the request's JWT refresh cookie.
     *
     * Returns null and logs the reason for any failure. The middleware
     * never raises; the worst case is that the inner handler runs
     * without a session attribute and downstream code treats the
     * request as unauthenticated.
     */
    private function resolveSession(ServerRequestInterface $request): ?HordeSession
    {
        $cookies = $request->getCookieParams();
        $jwt = $cookies[self::COOKIE_NAME] ?? null;
        if (!is_string($jwt) || $jwt === '') {
            return null;
        }

        try {
            $verified = $this->jwtService->verifyRefreshToken($jwt);
        } catch (InvalidArgumentException $e) {
            // Expired, malformed, or signature-rejected. The session row
            // (if it exists) is no longer reachable by this token. Treat
            // as unauthenticated and let downstream code redirect to
            // login.
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

        try {
            $session = $this->sessionHandler->load(new SessionId($jti));
        } catch (SessionException $e) {
            // Backend storage error. Same outcome as a missing row:
            // unauthenticated.
            $this->logger->warning('JWT session lookup failed', [
                'jti' => $jti,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        if ($session === null) {
            // Token is valid (signature OK, not expired) but the session
            // row is gone. Logout destroyed it; or it was pruned by GC.
            // Token is effectively dead.
            $this->logger->info('JWT session row not found', ['jti' => $jti]);
            return null;
        }

        if (!$session instanceof HordeSession) {
            // SessionHandler is configured with a non-HordeSession
            // factory. Defensive check; should not happen in production.
            $this->logger->warning('JWT session row is not a HordeSession', [
                'jti' => $jti,
                'class' => get_debug_type($session),
            ]);
            return null;
        }

        return $session;
    }
}
