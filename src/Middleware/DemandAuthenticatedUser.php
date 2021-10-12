<?php
declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * returns 401 response if not authenticated
 * 
 * Reads attribute: 
 * - HORDE_AUTHENTICATED_USER the uid, if authenticated
 * 
 */
class DemandAuthenticatedUser implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute('HORDE_AUTHENTICATED_USER')) {
            return $handler->handle($request);
        }
        return $this->responseFactory->createResponse(401);
    }
}
