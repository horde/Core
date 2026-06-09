<?php

declare(strict_types=1);

/**
 * Conditional CSRF middleware for the AJAX dispatch stack.
 *
 * Enforces session token validation for session-authenticated requests
 * (which are vulnerable to CSRF via automatic cookie attachment) and
 * skips it for JWT-authenticated requests (where the Authorization
 * header is never sent automatically by the browser).
 *
 * Token sources checked in order:
 * 1. POST body parameter 'token' (legacy hordecore.js sends this)
 * 2. 'Horde-Session-Token' header (modern clients)
 *
 * On failure, returns a 403 JSON response with a 'horde.ajaxtimeout'
 * message matching the HordeCore JS client's expectations.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @package   Core
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Middleware;

use Horde\Core\Controller\Traits\JsonResponseTrait;
use Horde\Core\Session\HordeSession;
use Horde\Token\Exception\TokenException;
use Horde\Token\Token;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF middleware that is conditional on the authentication method.
 *
 * Intended for the three-form AJAX route where both JWT and session
 * auth are supported. Session-authed requests need CSRF protection;
 * JWT-authed requests (Bearer header) do not, because the browser
 * never attaches that header automatically.
 */
class ConditionalCsrfMiddleware implements MiddlewareInterface
{
    use JsonResponseTrait;

    /**
     * @param Token $tokenService  CSRF token validator. Tokens must be
     *                             signed with {@see HordeSession::CSRF_SEED}
     *                             so the middleware accepts the same tokens
     *                             the legacy Horde_Session shim emits.
     */
    public function __construct(
        private readonly Token $tokenService,
    ) {}

    /**
     * Process an incoming request.
     *
     * If the request was authenticated via JWT (auth_type attribute set
     * to 'jwt'), CSRF validation is skipped entirely. Otherwise, a
     * session token is required and validated.
     *
     * @param ServerRequestInterface  $request  The incoming request
     * @param RequestHandlerInterface $handler  The next handler
     *
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if ($request->getAttribute('auth_type') === 'jwt') {
            return $handler->handle($request);
        }

        $token = $this->extractToken($request);
        if ($token === '') {
            return $this->csrfFailureResponse();
        }

        try {
            $valid = $this->tokenService->isValid($token, HordeSession::CSRF_SEED);
        } catch (TokenException) {
            $valid = false;
        }

        if (!$valid) {
            return $this->csrfFailureResponse();
        }

        return $handler->handle($request);
    }

    /**
     * Extract the CSRF token from the request.
     *
     * Checks the POST body 'token' parameter first (legacy hordecore.js
     * path), then falls back to the 'Horde-Session-Token' header.
     *
     * @param ServerRequestInterface $request  The incoming request
     *
     * @return string  The token value, or empty string if not found
     */
    private function extractToken(ServerRequestInterface $request): string
    {
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body['token']) && is_string($body['token'])) {
            return $body['token'];
        }

        $header = $request->getHeaderLine('Horde-Session-Token');
        if ($header !== '') {
            return $header;
        }

        return '';
    }

    /**
     * Build a 403 response matching the HordeCore SessionTimeout pattern.
     *
     * The response contains a 'horde.ajaxtimeout' message that the
     * HordeCore JS client recognises and handles with a logout redirect.
     *
     * @return ResponseInterface
     */
    private function csrfFailureResponse(): ResponseInterface
    {
        return $this->jsonResponse([
            'response' => false,
            'msgs' => [
                [
                    'type' => 'horde.ajaxtimeout',
                    'message' => 'CSRF token missing or invalid.',
                    'flags' => [],
                ],
            ],
        ], 403);
    }
}
