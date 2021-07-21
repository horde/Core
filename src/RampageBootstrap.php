<?php
namespace Horde\Core;

use Horde\Core\Middleware\AppFinder;
use Horde\Core\Middleware\AppRouter;
use Horde\Http\RequestFactory;
use Horde\Http\UriFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\Server\RampageRequestHandler;
use Horde\Http\Server\RequestBuilder;
use Horde\Http\Server\ResponseWriterWeb;
use Horde\Http\Server\Runner;
use Horde\Http\Server\Middleware\Responder;
use Horde\Http\StreamFactory;
use Horde\Core\Middleware\HordeCore as HordeCoreMiddleware;
use Horde_Injector;
use Horde_Injector_TopLevel;

/**
 * Bootstrap the Rampage HTTP endpoint
 * 
 * 
 */
class RampageBootstrap
{
    public static function run() 
    {
        $requestFactory = new RequestFactory();
        $streamFactory = new StreamFactory();
        $uriFactory = new UriFactory();
        $responseFactory = new ResponseFactory();
        
        // Build the request from server variables.
        // The RequestBuilder could easily be autowired by a DI container.
        $requestBuilder = new RequestBuilder($requestFactory, $streamFactory, $uriFactory);
        $request = $requestBuilder->withGlobalVariables()->build();
        $injector = new Horde_Injector_TopLevel;
        $middlewares = [
            // TODO: Unconditionally setup the output compressor, it should act only upon an attribute
            // TODO: Unconditionally setup the error handler
            // Setup the horde init middleware. It will add more middleware to the stack
            new HordeCoreMiddleware(),
        ];
        
        $handler = new RampageRequestHandler($responseFactory, $streamFactory, $middlewares);
        $runner = new Runner($handler, new ResponseWriterWeb());
        $runner->run($request);
    }
}