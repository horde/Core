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
use Horde\Injector\Injector;
use Horde\Core\RuntimeRoutesProvider;
use Horde\Core\Uri\RoutesProvider;
use Horde\Exception\HordeException;
use Horde_Controller;
use Horde_Registry;
use Horde_Application;
use Horde_Exception;
use Horde_Injector;

/**
 * HordeCore middleware — takeover middleware for legacy routes
 *
 * Initializes the full Horde Application Framework environment, then
 * resolves and dispatches the remaining middleware stack and controller
 * from the legacy $GLOBALS['injector']. Never calls the outer handler.
 */
class HordeCore implements MiddlewareInterface
{
    /**
     * Constructor for the HordeCore middleware.
     *
     * Make sure anything already set up by the rampage bootstrap isn't contradicted by HordeCore middleware defaults or legacy registry setup.
     *
     * @param Injector|null $injector The dependency injector instance, optional for BC reasons. Make mandatory in H7
     */
    public function __construct(private ?Injector $injector) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!class_exists('Horde_Application')) {
            throw new Horde_Exception('Autoloading issue');
        }
        if (!class_exists('Horde_Registry')) {
            throw new Horde_Exception('Autoloading issue');
        }

        // Ensure we have an injector instance
        if ($this->injector === null) {
            // Fallback path - we should not arrive here unless there is some undocumented usage pattern
            if (isset($GLOBALS['injector']) && $GLOBALS['injector'] instanceof Injector) {
                $this->injector = $GLOBALS['injector'];
            } else {
                throw new Exception('Injector not provided');
            }
        }

        /**
         * HordeCore middleware is for the legacy stack driven by globals and Horde_Registry. Installing the global here is legitimate and expected.
         *
         * Ensure Horde_Registry has access to the same injector which originally handled the HTTP request and potentially already setup session management.
         * Registry checks the global before setting up its own injector instance as a fallback.
         * @See discussion https://github.com/horde/Core/pull/227/changes#r4041232116
         */
        $GLOBALS['injector'] = $injector = $this->injector;

        // Initialize legacy Horde environment (globals, session, conf, etc.)
        Horde_Registry::appInit('horde', ['authentication' => 'none']);
        if (!$injector->has(Horde_Injector::class)) {
            $injector->setInstance(Horde_Injector::class, $injector);
        }
        if (!$injector->has(Injector::class)) {
            $injector->setInstance(Injector::class, $injector);
        }
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

        $registry = $injector->get(Horde_Registry::class);
        $request = $request->withAttribute('registry', $registry);

        // Bridge RuntimeRoutesProvider into legacy injector so controllers can use RoutesProvider
        $mapper = $request->getAttribute('mapper');
        if ($mapper instanceof RuntimeRoutesProvider) {
            $injector->setInstance(RuntimeRoutesProvider::class, $mapper);
            $injector->setInstance(RoutesProvider::class, $mapper);
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
                $controller = $injector->get($controllerName);
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
