<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Horde\Core\Session\HordeSession;
use Horde\Token\Exception\TokenException;
use Horde\Token\Token;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * DemandSessionToken middleware
 *
 * Validates that the request carries a valid session-bound CSRF token in the
 * `Horde-Session-Token` header. Tokens are HMAC-derived via
 * {@see Token::isValid()} using {@see HordeSession::CSRF_SEED} so this
 * middleware accepts the same tokens the legacy
 * `Horde_Session::getToken()` shim emits.
 *
 * @author    Mahdi Pasche <pasche@b1-systems.de>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2013-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class DemandSessionToken implements MiddlewareInterface
{
    public function __construct(
        protected ResponseFactoryInterface $responseFactory,
        protected StreamFactoryInterface $streamFactory,
        protected Token $tokenService,
    ) {}

    /**
     * Checks for a valid session token. Returns 403 if the token is missing
     * or invalid.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // getHeaderLine forces a single value for the header to be valid.
        $token = $request->getHeaderLine('Horde-Session-Token');
        try {
            $valid = $this->tokenService->isValid($token, HordeSession::CSRF_SEED);
        } catch (TokenException) {
            $valid = false;
        }

        if (!$valid) {
            return $this->responseFactory->createResponse(
                403,
                'Horde-Session-Token header missing or incorrect.'
            );
        }

        return $handler->handle($request);
    }
}

