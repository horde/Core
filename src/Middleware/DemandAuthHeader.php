<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde_Registry;
use Horde_Application;
use Horde_Auth_Base;
use Horde_Core_Auth_Application;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * DemandAuthHeader middleware
 *
 * Purpose: Send appropriate response if no auth header present
 *
 * Check if an Authorization header of any kind is present
 *
 * If the header is missing, send a 401 Auth Required response
 * Include a WWW-Authenticate header with a realm and method
 *
 */
class DemandAuthHeader implements MiddlewareInterface
{
    private string $challenge;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        string $type = 'BASIC',
        string $realm = '',
        string $charset = 'UTF-8'
    ) {
        $this->responseFactory = $responseFactory;
        $this->challenge = "$type realm=\"$realm\"";
        if ($charset) {
            $this->challenge .= ", charset=\"$charset\"";
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check if request HAS an auth header
        if (!$request->hasHeader('Authorization')) {
            $response = $this->responseFactory->createResponse(401, 'Unauthorized');
            return $response->withHeader('WWW-Authenticate', $this->challenge);
        }
        return $handler->handle($request);
    }
}
