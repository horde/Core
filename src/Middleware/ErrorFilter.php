<?php
declare(strict_types=1);

namespace Horde\Core\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use \Horde_Registry;
use \Horde_Application;

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
        $hordeEnv = Horde_Registry::appInit('horde', array('authentication' => 'none'));
        // Bad! the injector should be part of the early init's response.
        $injector = $GLOBALS['injector'];
        $request->withAttribute('dic', $injector);
        // Detect correct app
        $registry = $injector->getInstance('Horde_Registry');
        $request->withAttribute('registry', $registry);

        

        // Setup Router for that app
        // Detect route in app. If route found, initialize the actual app environment. If not, produce an error.
        // Push more middleware on the stack
        // If the detected route's handler is a Horde_Controller, put it into a wrapper middleware.
        // Initialize the actual application

        return $handler->handle($request);
    }
}