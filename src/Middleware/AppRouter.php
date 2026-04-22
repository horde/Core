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
use Horde\Http\Server\RampageRequestHandler;
use Horde_Registry;
use Horde_Application;
use Horde_Controller;
use Horde_Injector;
use Horde\Routes\Mapper;
use Horde\Routes\Matcher;
use Horde\Routes\MatchResult;
use Horde_String;
use Psr\Http\Message\ResponseFactoryInterface;
use Horde\Exception\HordeException;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\BackendConfigLoader;
use Horde\Core\Config\PrefsConfigLoader;
use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Config\Vhost;

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
class AppRouter extends RampageRequestHandler implements MiddlewareInterface, RequestHandlerInterface
{
    private Mapper $mapper;
    private Horde_Registry $registry;
    private Horde_Injector $injector;

    public function __construct(Horde_Registry $registry, Mapper $mapper, Horde_Injector $injector)
    {
        $this->registry = $registry;
        $this->mapper = $mapper;
        $this->injector = $injector;
    }

    /**
     * Route a request for a horde app
     *
     * Depends on the AppFinder running first
     * This middleware really only works with the Rampage Runner
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Setup ConfigLoader in DI container if not already present
        if (!$this->injector->has(ConfigLoader::class)) {
            if (!defined('HORDE_CONFIG_BASE')) {
                throw new Exception('HORDE_CONFIG_BASE not defined');
            }
            $this->injector->setInstance(
                ConfigLoader::class,
                new ConfigLoader(HORDE_CONFIG_BASE, new Vhost())  // Auto-detect vhost
            );
        }

        // Setup BackendConfigLoader in DI container if not already present
        if (!$this->injector->has(BackendConfigLoader::class)) {
            if (!defined('HORDE_CONFIG_BASE')) {
                throw new Exception('HORDE_CONFIG_BASE not defined');
            }
            // Vendor base = composer vendor/horde/ directory
            $vendorBase = dirname(__DIR__, 4) . '/vendor/horde';
            $this->injector->setInstance(
                BackendConfigLoader::class,
                new BackendConfigLoader(HORDE_CONFIG_BASE, $vendorBase, new Vhost())  // Auto-detect vhost
            );
        }

        // Setup PrefsConfigLoader in DI container if not already present
        if (!$this->injector->has(PrefsConfigLoader::class)) {
            $vendorBase = dirname(__DIR__, 4) . '/vendor/horde';
            $this->injector->setInstance(
                PrefsConfigLoader::class,
                new PrefsConfigLoader(HORDE_CONFIG_BASE, $vendorBase, new Vhost())  // Auto-detect vhost
            );
        }

        // Setup RegistryConfigLoader in DI container if not already present
        if (!$this->injector->has(RegistryConfigLoader::class)) {
            // Vendor base = horde base app directory
            $vendorBase = dirname(__DIR__, 4) . '/vendor/horde/horde';
            $this->injector->setInstance(
                RegistryConfigLoader::class,
                new RegistryConfigLoader(HORDE_CONFIG_BASE, $vendorBase, new Vhost())  // Auto-detect vhost
            );
        }

        $app = $request->getAttribute('app');
        $prefix = $request->getAttribute('routerPrefix');
        if (is_null($prefix)) {
            throw new Exception("Missing Attribute: 'routerPrefix'");
        }
        if (empty($app)) {
            throw new Exception("Missing Attribute: 'app'");
        }
        $defaultStack = [
            AuthHordeSession::class,
            RedirectToLogin::class,
        ];

        // Check for route definitions.
        $fileroot = $this->registry->get('fileroot', $app);
        $routeFile = $fileroot . '/config/routes.php';
        if (!file_exists($routeFile)) {
            throw new Exception("No Routes file found for App $app");
        }

        // TODO: Should this move to another middleware?

        // Before PushApp, we need to load the Horde Autoloader
        // Push $app onto the registry
        $this->registry->pushApp($app);

        // Application routes are relative only to the application. Let the
        // mapper know where they start.
        $this->mapper->prefix = $prefix;

        // Load application routes.
        // Cannot rename mapper as long as we support the existing routes definitions
        $mapper = $router = $this->mapper;
        include $routeFile;
        if (file_exists($fileroot . '/config/routes.local.php')) {
            include $fileroot . '/config/routes.local.php';
        }

        // Match using PSR-7 Matcher (auto-populates environ from request)
        // @TODO Cache routes
        $matcher = new Matcher($this->mapper, $request);
        $matchResult = $matcher->getMatchResult();

        // Build plain array for backward compatibility
        $match = $matchResult !== null ? $matchResult->toArray() : [];

        // Set the typed MatchResult as a request attribute
        $request = $request->withAttribute('matchResult', $matchResult);
        // Backward compat: also set the plain array as 'route'
        $request = $request->withAttribute('route', $match);

        // Bind MatchResult in injector so controllers can type-hint it
        if ($matchResult !== null) {
            $this->injector->setInstance(MatchResult::class, $matchResult);
        }

        // compatibility: if unset stack and HordeAuthType is 'NONE' set empty stack
        if (!isset($match['stack']) && ($match['HordeAuthType'] ?? null) === 'NONE') {
            $match['stack'] = [];
        }
        // Stack is an array of DI keys
        // Empty array means NO more middleware besides controller
        // unset stack means DEFAULT middleware stack
        $stack = $match['stack'] ?? $defaultStack;

        // DEBUG - Only log if HORDE_DEBUG_ROUTER is set
        if (getenv('HORDE_DEBUG_ROUTER')) {
            // Use web-writable var directory
            $varDir = dirname($fileroot, 2) . '/var';
            if (!is_dir($varDir)) {
                $varDir = '/tmp';
            }
            $debugLog = $varDir . '/approuter-debug.log';
            file_put_contents($debugLog, "=== AppRouter Debug ===\n", FILE_APPEND);
            file_put_contents($debugLog, 'Time: ' . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'Route: ' . ($match['name'] ?? 'UNNAMED') . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'Controller: ' . ($match['controller'] ?? 'NONE') . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'HordeAuthType: ' . ($match['HordeAuthType'] ?? 'NOT SET') . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'stack isset: ' . (isset($match['stack']) ? 'YES' : 'NO') . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'stack value: ' . json_encode($match['stack'] ?? 'NOT SET') . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'Using stack: ' . json_encode($stack) . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'Stack count: ' . count($stack) . "\n", FILE_APPEND);
        }

        foreach ($stack as $middleware) {
            if (getenv('HORDE_DEBUG_ROUTER')) {
                file_put_contents($debugLog, 'Adding middleware: ' . $middleware . "\n", FILE_APPEND);
            }
            $handler->addMiddleware($this->injector->get($middleware));
        }

        if (getenv('HORDE_DEBUG_ROUTER')) {
            file_put_contents($debugLog, "=== End AppRouter Debug ===\n\n", FILE_APPEND);
        }

        // Controller is a single DI key for either a HandlerInterface, MiddlewareInterface or a Horde_Controller
        $controllerName = $match['controller'] ?? '';
        $traditionalFilename = $fileroot . '/app/controllers/' . $controllerName . '.php';
        $controller = null;
        if ($controllerName) {
            try {
                $controller = $this->injector->getInstance($controllerName);
            } catch (Exception $e) {
                if (empty($controller)) {
                    if (file_exists($traditionalFilename)) {
                        require_once $traditionalFilename;
                        $traditionalName = Horde_String::ucfirst($app) . '_' . Horde_String::ucfirst($controllerName) . '_Controller';
                        if ($this->injector->hasInstance($traditionalName) || class_exists($traditionalName)) {
                            $controller = $this->injector->getInstance($traditionalName);
                        }
                    } else {
                        throw new HordeException('Defined controller but could not create: ' . $controllerName . ' — ' . $e->getMessage(), 0, $e);
                    }
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
