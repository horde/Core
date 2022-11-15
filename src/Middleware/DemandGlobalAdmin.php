<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * DemandGlobalAdmin middleware
 * Returns 403 response if authenticated user is not global admin
 *
 * Reads attribute:
 * - HORDE_GLOBAL_ADMIN true if the authenticated user is a global admin
 *
 * @author    Mahdi Pasche <pasche@b1-systems.de>
 * @category  Horde
 * @copyright 2013-2022 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class DemandGlobalAdmin implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute('HORDE_GLOBAL_ADMIN')) {
            return $handler->handle($request);
        }
        return $this->responseFactory->createResponse(403, 'No Permission');
    }
}
