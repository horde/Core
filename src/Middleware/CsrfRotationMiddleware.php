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
use Horde\Token\Token;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Outbound CSRF rotation middleware.
 *
 * After the inner handler returns, mints a fresh CSRF token bound to
 * the current session and writes it to the `X-Csrf-Token` response
 * header. Routes that compose this middleware participate in a uniform
 * "every response can carry a fresh CSRF" contract that the standalone
 * {@see \Horde\Base\Js\SessionApiClient} (and any future modern JS
 * client) relies on.
 *
 * Pairs with {@see ConditionalCsrfMiddleware} which validates the
 * inbound token on state-changing requests. Rotation middleware sits
 * **after** validation in the chain so that an inbound request with a
 * stale token gets rejected (419) before the controller runs and
 * before a new token is minted — preventing a rotation oracle.
 *
 * No-op when no session is attached to the request. A response carrying
 * no `X-Csrf-Token` header signals to the client that no CSRF state
 * exists to update; the client retains whatever token it had.
 *
 * Operates entirely against PSR-15 interfaces and {@see HordeSession}.
 * Does NOT import {@see \Horde_Registry}, does NOT read `$GLOBALS`,
 * does NOT call PHP's session module directly.
 */
final class CsrfRotationMiddleware implements MiddlewareInterface
{
    /** Response header carrying the rotated CSRF token. */
    public const RESPONSE_HEADER = 'X-Csrf-Token';

    public function __construct(
        private readonly Token $tokenService,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        $session = $request->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
        if (!$session instanceof HordeSession) {
            // No session to bind a token to. The client carries no CSRF
            // state for this request; nothing to rotate. Leave the
            // response untouched.
            return $response;
        }

        $generated = $this->tokenService->generate(HordeSession::CSRF_SEED);

        return $response->withHeader(self::RESPONSE_HEADER, $generated->token);
    }
}
