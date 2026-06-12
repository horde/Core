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

use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionConfig;
use Horde\Http\SameSite;
use Horde\Http\Server\Cookies;
use Horde\Http\StrictCookie;
use Horde\SessionHandler\Exception\SessionException;
use Horde\SessionHandler\Session;
use Horde\SessionHandler\SessionHandler;
use Horde\SessionHandler\SessionId;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Modern PSR-15 cookie-bearing session middleware.
 *
 * Reads the session id from the configured session cookie, loads the
 * matching {@see HordeSession} via {@see SessionHandler::load()}, and
 * sets it as the request attribute {@see ATTRIBUTE_SESSION}. Mints a
 * fresh session via {@see SessionHandler::create()} when no cookie is
 * present or the cookie's row has been pruned.
 *
 * On the way out:
 *
 * - If the session was {@see HordeSession::markDestroyed()}: deletes
 *   the row via {@see SessionHandler::destroySession()} and emits a
 *   clearing `Set-Cookie` header.
 * - If the session was {@see HordeSession::scheduleRegeneration()}:
 *   rotates the id via {@see SessionHandler::regenerate()}, persists
 *   the new row, and emits a `Set-Cookie` carrying the new id.
 * - Otherwise: persists the row if dirty, and emits `Set-Cookie` only
 *   when the request did not arrive with the same id.
 *
 * Composition with {@see JwtSessionLoader}: when JwtSessionLoader has
 * already set {@see ATTRIBUTE_SESSION} on the request (because the
 * `horde_jwt_refresh` cookie resolved a session), this middleware skips
 * loading and only handles persistence on the way out.
 *
 * Operates entirely against {@see SessionHandler} primitives. Does NOT
 * call PHP's session module, the legacy {@see \Horde_Session} shim, or
 * {@see \Horde\Core\Session\SessionLifecycle}'s synchronous executors.
 * The legacy stack continues to use the shim and the lifecycle directly.
 */
final class HordeSessionMiddleware implements MiddlewareInterface
{
    /** Request attribute carrying the active {@see HordeSession}. */
    public const ATTRIBUTE_SESSION = JwtSessionLoader::ATTRIBUTE_SESSION;

    public function __construct(
        private readonly SessionHandler $handler,
        private readonly SessionConfig $config,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $existingSession = $request->getAttribute(self::ATTRIBUTE_SESSION);
        $hadCookie = false;

        if ($existingSession instanceof HordeSession) {
            // A previous middleware (typically JwtSessionLoader) already
            // resolved a session for this request. Use it.
            $session = $existingSession;
            $hadCookie = true;
        } else {
            [$session, $hadCookie] = $this->loadOrMint($request);
            $request = $request->withAttribute(self::ATTRIBUTE_SESSION, $session);
        }

        $response = $handler->handle($request);

        return $this->finalise($response, $session, $hadCookie);
    }

    /**
     * Resolve the session for this request.
     *
     * Returns the session and whether the request arrived with a valid
     * cookie that resolved to it. The boolean drives whether a fresh
     * `Set-Cookie` is needed on the response.
     *
     * @return array{0: HordeSession, 1: bool}
     */
    private function loadOrMint(ServerRequestInterface $request): array
    {
        $cookies = $request->getCookieParams();
        $cookieValue = $cookies[$this->config->cookieName] ?? null;

        if (is_string($cookieValue) && $cookieValue !== '') {
            $session = $this->safeLoad($cookieValue);
            if ($session !== null) {
                return [$session, true];
            }
        }

        return [$this->mintFreshSession(), false];
    }

    private function safeLoad(string $cookieValue): ?HordeSession
    {
        try {
            $sessionId = new SessionId($cookieValue);
        } catch (InvalidArgumentException $e) {
            $this->logger->debug('Session cookie value rejected as malformed id', [
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        try {
            $loaded = $this->handler->load($sessionId);
        } catch (SessionException $e) {
            $this->logger->warning('Session backend load failed', [
                'sid' => $cookieValue,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        if ($loaded === null) {
            return null;
        }
        if (!$loaded instanceof HordeSession) {
            // SessionHandler factory configured against a non-HordeSession.
            // Defensive check; should not happen in production.
            $this->logger->warning('Loaded session is not a HordeSession', [
                'class' => get_debug_type($loaded),
            ]);
            return null;
        }
        return $loaded;
    }

    private function mintFreshSession(): HordeSession
    {
        $created = $this->handler->create();
        if (!$created instanceof HordeSession) {
            // The configured factory is supposed to mint HordeSessions;
            // if it doesn't, the rest of the modern stack will fail
            // anyway. Surface the misconfiguration clearly.
            throw new \LogicException(sprintf(
                'SessionHandler::create() returned %s, expected HordeSession.',
                get_debug_type($created),
            ));
        }
        return $created;
    }

    /**
     * Persist + emit cookie on the way out.
     */
    private function finalise(
        ResponseInterface $response,
        HordeSession $session,
        bool $hadCookie,
    ): ResponseInterface {
        if ($session->isDestroyed()) {
            return $this->finaliseDestroyed($response, $session);
        }

        if ($session->shouldRegenerate()) {
            return $this->finaliseRegenerated($response, $session);
        }

        return $this->finaliseSteady($response, $session, $hadCookie);
    }

    private function finaliseDestroyed(
        ResponseInterface $response,
        HordeSession $session,
    ): ResponseInterface {
        try {
            $this->handler->destroySession($session->getId());
        } catch (SessionException $e) {
            // Backend write failed during destroy. The user's intent was
            // logout; we cannot rebound silently. Log and still emit
            // the clearing cookie so the client treats the session as
            // gone.
            $this->logger->warning('Session destroy failed', [
                'sid' => (string) $session->getId(),
                'exception' => $e->getMessage(),
            ]);
        }

        if ($this->config->cookieDisabled) {
            // Cookieless mode: client identifies via Authorization header.
            // The backend row was destroyed above; nothing to clear on
            // the wire.
            return $response;
        }

        return Cookies::clear(
            $response,
            $this->config->cookieName,
            $this->config->cookiePath,
            $this->config->cookieDomain,
        );
    }

    private function finaliseRegenerated(
        ResponseInterface $response,
        HordeSession $session,
    ): ResponseInterface {
        try {
            $rotated = $this->handler->regenerate($session);
        } catch (SessionException $e) {
            // Failed to rotate. Best-effort: persist what we have under
            // the original id so the user's request work isn't lost,
            // and don't emit a Set-Cookie. Next request retries
            // rotation if the marker is set again.
            $this->logger->warning('Session regenerate failed; persisting under original id', [
                'sid' => (string) $session->getId(),
                'exception' => $e->getMessage(),
            ]);
            return $this->finaliseSteady($response, $session, hadCookie: true);
        }

        // Refresh the regenerate-at deadline. Two reasons:
        //   1. Per-request rotations should reset the deadline; otherwise
        //      a controller calling scheduleRegeneration() repeatedly
        //      would not extend the next forced-rotation window.
        //   2. SessionHandler::regenerate() returns a clean (non-dirty)
        //      session because it merely restores the old payload under
        //      a new id. Writing the deadline marks it dirty so save()
        //      actually persists the row at the new id; without this,
        //      the rotated session has no backend representation
        //      because the old row was deleted by regenerate().
        $rotated->setRegenerationDeadline(
            time() + $this->config->regenerateInterval,
        );

        try {
            $this->handler->save($rotated);
        } catch (SessionException $e) {
            $this->logger->warning('Session save after regenerate failed', [
                'sid' => (string) $rotated->getId(),
                'exception' => $e->getMessage(),
            ]);
        }

        if ($this->config->cookieDisabled) {
            // Cookieless mode: rotated id lives only on the backend row.
            // The client carries no cookie to update; the next request
            // continues to identify via Authorization header.
            return $response;
        }

        return Cookies::with($response, $this->buildSessionCookie((string) $rotated->getId()));
    }

    private function finaliseSteady(
        ResponseInterface $response,
        HordeSession $session,
        bool $hadCookie,
    ): ResponseInterface {
        $this->saveIfDirty($session);

        if ($this->config->cookieDisabled) {
            // Cookieless mode: never emit Set-Cookie regardless of
            // whether the request arrived with one.
            return $response;
        }

        if (!$hadCookie) {
            // First request, or cookie was missing/invalid: emit the
            // freshly-minted session id.
            return Cookies::with($response, $this->buildSessionCookie((string) $session->getId()));
        }

        // Steady state: cookie already present and id unchanged. Skip
        // the cookie emission.
        return $response;
    }

    private function saveIfDirty(Session $session): void
    {
        if (!$session->isDirty()) {
            return;
        }
        try {
            $this->handler->save($session);
        } catch (SessionException $e) {
            $this->logger->warning('Session save failed', [
                'sid' => (string) $session->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function buildSessionCookie(string $value): StrictCookie
    {
        return new StrictCookie(
            cookieName: $this->config->cookieName,
            cookieValue: $value,
            cookieMaxAge: $this->config->lifetime > 0 ? $this->config->lifetime : null,
            cookieDomain: $this->config->cookieDomain,
            cookiePath: $this->config->cookiePath,
            cookieSecure: $this->config->secure,
            cookieHttpOnly: true,
            cookieSameSite: SameSite::Lax,
        );
    }
}
