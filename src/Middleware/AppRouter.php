<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Horde;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde_Registry;
use Horde_Application;
use Horde_Controller;
use Horde_Injector;
use Horde_Routes_Mapper as Router;
use Horde_String;
use Psr\Http\Message\ResponseFactoryInterface;
use Horde\Exception\HordeException;

/**
 * AppRouter middleware
 *
 * Purpose:
 *
 * Run the router for the app from the attribute
 * Retrieve the route specific stack
 * If no route found, present a helpful but security-wise acceptable response
 *
 * Requires Attributes:
 * - app
 * - prefix
 *
 * Sets Attributes:
 *
 *
 */
class AppRouter implements MiddlewareInterface
{
    private Router $router;
    private Horde_Registry $registry;
    private Horde_Injector $injector;

    public function __construct(Horde_Registry $registry, Router $router, Horde_Injector $injector)
    {
        $this->registry = $registry;
        $this->router = $router;
        $this->injector = $injector;
    }

    /**
     * Route a request for a horde app
     *
     * Depends on the AppFinder running first
     * This middleware really only works with the Rampage Runner
     *
     * @param ServerRequestInterfacd $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $app = $request->getAttribute('app');
        $prefix = $request->getAttribute('routerPrefix');
        if (is_null($prefix)) {
            throw new \Exception("Missing Attribute: 'routerPrefix'");
        }
        if (empty($app)) {
            throw new \Exception("Missing Attribute: 'app'");
        }
        $defaultStack = [
            AuthHordeSession::class,
            RedirectToLogin::class,
        ];

        // Check for route definitions.
        $fileroot = $this->registry->get('fileroot', $app);
        $routeFile = $fileroot . '/config/routes.php';
        if (!file_exists($routeFile)) {
            throw new \Exception("No Routes file found for App $app");
        }

        // TODO: Should this move to another middleware?

        // Before PushApp, we need to load the Horde Autoloader
        // Push $app onto the registry
        $this->registry->pushApp($app);

        // Application routes are relative only to the application. Let the
        // mapper know where they start.
        $this->router->prefix = $prefix;

        // Load application routes.
        // Cannot rename mapper as long as we support the existing routes definitions
        $mapper = $router = $this->router;
        $router->environ = ['REQUEST_METHOD' => $request->getMethod()];
        include $routeFile;
        if (file_exists($fileroot . '/config/routes.local.php')) {
            include $fileroot . '/config/routes.local.php';
        }
        // Match
        // @TODO Cache routes
        $path = $request->getUri()->getPath();
        $path = strtok($path, '?');
        $match = $this->router->match($path);
        $request = $request->withAttribute('route', $match);

        // compatibility: if unset stack and HordeAuthType is 'NONE' set empty stack
        if (!isset($match['stack']) && $match['HordeAuthType'] === 'NONE') {
            $match['stack'] = [];
        }
        // Stack is an array of DI keys
        // Empty array means NO more middleware besides controller
        // unset stack means DEFAULT middleware stack
        $stack = $match['stack'] ?? $defaultStack;
        foreach ($stack as $middleware) {
            $handler->addMiddleware($this->injector->get($middleware));
        }

        // Controller is a single DI key for either a HandlerInterface, MiddlewareInterface or a Horde_Controller
        $controllerName = $match['controller'] ?? '';
        $traditionalFilename = $fileroot . '/app/controllers/' . $controllerName . '.php';
        if ($controllerName) {
            try {
                $controller = $this->injector->getInstance($controllerName);
            } catch (Exception $e) {
                throw new HordeException('Defined controller but could not create: ' . $controllerName, 0, $e);
            }
        }
        if (empty($controller)) {
            if (file_exists($traditionalFilename)) {
                require_once $traditionalFilename;
                $traditionalName = Horde_String::ucfirst($app) . '_' . Horde_String::ucfirst($controllerName) . '_Controller';
                if ($this->injector->hasInstance($traditionalName) || class_exists($traditionalName)) {
                    $controller = $this->injector->getInstance($traditionalName);
                }
            }
        }

        // Handle traditional Horde_Controller
        if ($controller instanceof Horde_Controller) {
            $middleware = new H5Controller(
                $controller,
                $this->injector->get(ResponseFactoryInterface::class),
                $this->injector->get(StreamFactoryInterface::class)
            );
            $handler->addMiddleware($middleware);
        }
        // Controllers can be implemented as a (final?) middleware
        if ($controller instanceof MiddlewareInterface) {
            $handler->addMiddleware($controller);
        }
        // Controllers can be implemented as a Payload RequestHandler
        if ($controller instanceof RequestHandlerInterface) {
            // Set controller as a payload handler
            // Simply calling controller would bypass any further middleware
            $handler->setPayloadHandler($controller);
        }
        return $handler->handle($request);
    }
}
