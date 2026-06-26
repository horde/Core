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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Inbound session-lifetime touch + outbound session-telemetry headers.
 *
 * On the way in (before the inner handler runs):
 *
 * - Touches the session's `_last_seen` slot to `time()`. The slot write
 *   marks the session dirty, so {@see HordeSessionMiddleware}'s outbound
 *   save-if-dirty persists the row, extending its backend TTL on file /
 *   SQL / cache backends that key off update timestamps.
 * - Reads the session's regeneration deadline via
 *   {@see HordeSession::getRegenerationDeadline()}. If the wall clock
 *   has passed it, calls {@see HordeSession::scheduleRegeneration()}.
 *   The intent flag is consumed by
 *   {@see HordeSessionMiddleware::finaliseRegenerated()} on response,
 *   which rotates the id, re-keys, and emits a fresh `Set-Cookie`.
 *
 * On the way out (after the inner handler returns):
 *
 * - Emits `X-Next-Ping` carrying the cadence in seconds the JS client
 *   should use for its next heartbeat. Computed as
 *   `lifetime / 3` with a floor of 60 seconds, falling back to the
 *   constructor's `$defaultNextPingSeconds` when the configured
 *   lifetime is 0 (browser-session lifetime).
 * - Emits `X-Session-Ts` carrying server `time()` so the client can
 *   detect clock skew against its local time.
 *
 * No-op when no session is attached to the request — neither the
 * inbound side effects nor the outbound headers happen, so the
 * middleware is safe to compose on routes that don't always have a
 * session.
 *
 * Reusable across any modern PSR-15 route that wants real traffic to
 * extend session lifetime and broadcast the keep-alive cadence. The
 * `/api/v1/session/ping` endpoint is the explicit consumer; any other
 * authenticated API endpoint that composes this middleware also keeps
 * the user's session alive for free.
 *
 * Operates entirely against PSR-15 interfaces and {@see HordeSession}.
 * Does NOT import {@see \Horde_Registry}, does NOT read `$GLOBALS`,
 * does NOT call PHP's session module directly.
 */
final class SessionLifetimeMiddleware implements MiddlewareInterface
{
    /** Response header carrying the next-ping cadence hint, in seconds. */
    public const HEADER_NEXT_PING = 'X-Next-Ping';

    /** Response header carrying the server's `time()` echo. */
    public const HEADER_SESSION_TS = 'X-Session-Ts';

    /** Session slot updated on every request through this middleware. */
    public const SLOT_LAST_SEEN = '_last_seen';

    /** Floor for `X-Next-Ping`, regardless of configured lifetime. */
    public const MIN_PING_INTERVAL = 60;

    public function __construct(
        private readonly SessionConfig $sessionConfig,
        private readonly int $defaultNextPingSeconds = 1200,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $session = $request->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);

        if ($session instanceof HordeSession) {
            $this->touchLifetime($session);
            $this->scheduleRotationIfDue($session);
        }

        $response = $handler->handle($request);

        if ($session instanceof HordeSession) {
            $response = $this->emitTelemetryHeaders($response);
        }

        return $response;
    }

    /**
     * Mark the session dirty so HordeSessionMiddleware persists it,
     * which on TTL-based backends refreshes the row's expiry.
     */
    private function touchLifetime(HordeSession $session): void
    {
        $session->setScoped('horde', self::SLOT_LAST_SEEN, time());
    }

    /**
     * Set the regeneration intent flag if the deadline has passed. The
     * actual rotation runs in {@see HordeSessionMiddleware::finaliseRegenerated()}.
     */
    private function scheduleRotationIfDue(HordeSession $session): void
    {
        $deadline = $session->getRegenerationDeadline();
        if ($deadline !== null && time() >= $deadline) {
            $session->scheduleRegeneration();
        }
    }

    /**
     * Add `X-Next-Ping` and `X-Session-Ts` to the response.
     */
    private function emitTelemetryHeaders(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader(self::HEADER_NEXT_PING, (string) $this->resolveNextPingSeconds())
            ->withHeader(self::HEADER_SESSION_TS, (string) time());
    }

    /**
     * Compute the next-ping cadence hint in seconds.
     *
     * `lifetime / 3` gives the client roughly three heartbeats per
     * session lifetime — enough margin to survive one missed ping
     * before the session would otherwise time out. Floor at 60 seconds
     * to keep the heartbeat sensible on aggressive short-lifetime
     * deployments. Fall back to the constructor default when lifetime
     * is 0 (PHP browser-session lifetime, no explicit timeout).
     */
    private function resolveNextPingSeconds(): int
    {
        $lifetime = $this->sessionConfig->lifetime;
        if ($lifetime <= 0) {
            return $this->defaultNextPingSeconds;
        }
        return max(self::MIN_PING_INTERVAL, (int) floor($lifetime / 3));
    }
}
