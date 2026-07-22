<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Middleware;

use Horde\Core\Session\SessionLifecycle;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Opt-in modern session establishment.
 *
 * Modern PSR-15 routes that need a live {@see \Horde\Core\Session\HordeSession}
 * — for direct reads via {@see \Horde\Core\Session\SessionAccess}, for
 * writes from controllers or services like `LoginService`, or for any
 * downstream middleware that reaches the injector-bound HordeSession —
 * include this middleware in their route stack.
 *
 * Routes that do NOT touch session state (pure API endpoints, static
 * assets, health checks) omit it and pay zero session-boot cost.
 *
 * Runs {@see SessionLifecycle::setup()} with `start=true`. Setup is
 * idempotent — safe to compose with other middleware or with the
 * legacy `Horde_Registry::appInit()` path that also calls `setup()`;
 * the second call is a no-op.
 *
 * Contrast with:
 * - {@see AuthHordeSession}: reads user identity from Registry after a
 *   session already exists. Does NOT establish session itself, so
 *   routes wanting authenticated-user resolution list this middleware
 *   before AuthHordeSession.
 * - {@see HordeSessionMiddleware}: the full modern middleware that
 *   loads via SessionHandler, emits Set-Cookie, orchestrates rotation
 *   at request boundaries. Used by pure PSR-15 stacks that own the
 *   whole request pipeline. This lightweight middleware exists for
 *   routes still mixed with legacy scaffolding.
 */
final class EstablishHordeSession implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionLifecycle $sessionLifecycle,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $this->sessionLifecycle->setup(start: true);
        return $handler->handle($request);
    }
}
