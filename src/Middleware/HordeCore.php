<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Horde\Http\RequestFactory;
use Horde\Http\UriFactory;
use Horde\Http\StreamFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\Server\RampageRequestHandler;
use Horde\Exception\HordeException;
use Horde_Controller;
use Horde_Registry;
use Horde_Application;
use Horde_Exception;

/**
 * HordeCore middleware — takeover middleware for legacy routes
 *
 * Initializes the full Horde Application Framework environment, then
 * resolves and dispatches the remaining middleware stack and controller
 * from the legacy $GLOBALS['injector']. Never calls the outer handler.
 */
class HordeCore implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!class_exists('Horde_Application')) {
            throw new Horde_Exception('Autoloading issue');
        }
        if (!class_exists('Horde_Registry')) {
            throw new Horde_Exception('Autoloading issue');
        }

        // Initialize legacy Horde environment (globals, session, conf, etc.)
        Horde_Registry::appInit('horde', ['authentication' => 'none']);
        $injector = $GLOBALS['injector'];
        $injector->setInstance('Horde_Injector', $injector);

        if (!$injector->has(UriFactoryInterface::class)) {
            $injector->setInstance(UriFactoryInterface::class, new UriFactory());
        }
        if (!$injector->has(StreamFactoryInterface::class)) {
            $injector->setInstance(StreamFactoryInterface::class, new StreamFactory());
        }
        if (!$injector->has(ServerRequestFactoryInterface::class)) {
            $injector->setInstance(ServerRequestFactoryInterface::class, new RequestFactory());
        }
        if (!$injector->has(ResponseFactoryInterface::class)) {
            $injector->setInstance(ResponseFactoryInterface::class, new ResponseFactory());
        }

        $registry = $injector->getInstance('Horde_Registry');
        $request = $request->withAttribute('registry', $registry);

        // Bridge RuntimeRoutesProvider into legacy injector so controllers can use urlFor()
        $mapper = $request->getAttribute('mapper');
        if ($mapper !== null) {
            $injector->setInstance(\Horde\Routes\Mapper::class, $mapper);
            $injector->setInstance(\Horde\Core\RuntimeRoutesProvider::class, $mapper);
        }

        // Push the identified app onto the legacy registry stack
        $app = $request->getAttribute('app');
        if ($app) {
            $registry->pushApp($app);
        }

        // Compute remaining stack (everything after HordeCore)
        $fullStack = $request->getAttribute('stack', []);
        $myPosition = array_search(self::class, $fullStack, true);
        $remainingStack = ($myPosition !== false)
            ? array_slice($fullStack, $myPosition + 1)
            : [];

        $controllerName = $request->getAttribute('controller', '');

        // Build internal pipeline — resolve from legacy injector
        $responseFactory = $injector->get(ResponseFactoryInterface::class);
        $streamFactory = $injector->get(StreamFactoryInterface::class);
        $internalHandler = new RampageRequestHandler($responseFactory, $streamFactory);

        foreach ($remainingStack as $mw) {
            $internalHandler->addMiddleware($injector->get($mw));
        }

        // Resolve controller
        if ($controllerName) {
            try {
                $controller = $injector->getInstance($controllerName);
            } catch (Exception $e) {
                throw new HordeException(
                    'Defined controller but could not create: ' . $controllerName . ' — ' . $e->getMessage(),
                    0,
                    $e
                );
            }

            if ($controller instanceof Horde_Controller) {
                $middleware = new H5Controller($controller, $responseFactory, $streamFactory);
                $internalHandler->addMiddleware($middleware);
            }
            if ($controller instanceof MiddlewareInterface) {
                $internalHandler->addMiddleware($controller);
            }
            if ($controller instanceof RequestHandlerInterface) {
                $internalHandler->setPayloadHandler($controller);
            }
        }

        return $internalHandler->handle($request);
    }
}
