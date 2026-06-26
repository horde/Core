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

namespace Horde\Core\Controller;

use Horde\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Generic terminal PSR-15 handler for routes whose response is fully
 * produced by middleware.
 *
 * Returns 204 No Content with an empty body. Mount on any route where
 * the middleware chain owns the complete response — for example a route
 * that runs {@see \Horde\Core\Middleware\CsrfRotationMiddleware} to emit
 * `X-Csrf-Token`, a readiness probe whose middleware sets the status
 * code, a future CORS preflight, or a session-invalidate endpoint where
 * middleware drives the destroy flag.
 *
 * Has no awareness of sessions, CSRF, identity, configuration, or any
 * other domain concept. Pure structural mount point.
 *
 * The strategy doc explains why two distinct API endpoints
 * (`/api/v1/session/ping` and `/api/v1/session/csrf-token`) share this
 * single controller: see
 * `horde-development/strategies/session-to-jwt/canonical-session-auth-csrf-strategy-2026-06-26.md`
 * §4.2.
 */
final class NoopController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(status: 204);
    }
}
