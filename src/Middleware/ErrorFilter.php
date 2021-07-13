<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde_Registry;
use Horde_Application;

/**
 * ErrorFilter middleware
 *
 * Purpose:
 *
 * Prevent ugly stack traces from showing up to users or APIs.
 * Give meaningful feedback and logging.
 * Can handle errors early in setup
 * Can give more meaningful feedback on a fully setup environment
 *
 * Intended to run close to top of stack
 *
 * Requires Attributes:
 *
 * Sets Attributes:
 *
 *
 */
class ErrorFilter implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // TODO:
        return $handler->handle($request);
    }
}
