<?php
declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use \Horde_Registry;
use \Horde_Application;
use Horde_Controller;
use Horde_Routes_Mapper as Router;
use \Horde_String;

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
        $defaultStack = [
            // TODO: Default stack
        ];

        $injector = $request->getAttribute('dic');
        $app = $request->getAttribute('app');
        $prefix = $request->getAttribute('routerPrefix');
        if (empty($prefix)) {
            throw new \Exception("Missing Attribute: 'routerPrefix'");
        }
        if (empty($app)) {
            throw new \Exception("Missing Attribute: 'app'");
        }
        if (empty($injector)) {
            throw new \Exception("Missing Attribute: 'dic'");
        }
        $registry = $injector->get(Horde_Registry::class);
        $this->router = $injector->get(Router::class);
        
        // Check for route definitions.
        $fileroot = $registry->get('fileroot', $app);
        $routeFile = $fileroot . '/config/routes.php';
        if (!file_exists($routeFile)) {
            throw new \Exception("No Routes file found for App");
        }

        // TODO: Should this move to another middleware?

        // Push $app onto the registry
        $registry->pushApp($app);

        // Application routes are relative only to the application. Let the
        // mapper know where they start.
        $this->router->prefix = $prefix;
        // Set the application controller directory
//        $this->router->directory = $registry->get('fileroot', $app) . '/app/controllers';

        // Load application routes.
        // Cannot rename mapper as long as we support the existing routes definitions
        $mapper = $router = $this->router;
        $router->environ = array('REQUEST_METHOD' => $request->getMethod());
        include $routeFile;
        if (file_exists($fileroot . '/config/routes.local.php')) {
            include $fileroot . '/config/routes.local.php';
        }
        // Match
        // @TODO Cache routes
        $path = $request->getUri()->getPath();
        // TODO: Shouldn't this be unnecessary by now? 
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        $match = $this->router->match($path);

        $request = $request->withAttribute('route', $match);

        // Stack is an array of DI keys
        // Empty array means NO more middleware besides controller
        // unset stack means DEFAULT middleware stack
        $stack = isset($match['stack']) ? $match['stack'] : $defaultStack;
        foreach ($stack as $middleware) {
            $handler->addMiddleware($middleware);
        }

        // Controller is a single DI key for either a HandlerInterface, MiddlewareInterface or a Horde_Controller
        $controllerName = $match['controller'] ?? '';
        $traditionalName = Horde_String::ucfirst($app) . '_' . Horde_String::ucfirst($controllerName) . '_Controller';
        if ($injector->has($controllerName) || class_exists($controllerName)) {
            $controller = $injector->get($controllerName);
        } elseif ($injector->has($traditionalName) || class_exists($traditionalName)) {
            $controller = $injector->get($traditionalName);
        }
        // Handle traditional Horde_Controller
        if ($controller instanceof Horde_Controller) {
            $middleware = $injector->createInstance(H5Controller::class);
            $handler->addMiddleware($middleware);
        }
        // Controllers can be implemented as a (final?) middleware
        if ($controller instanceof MiddlewareInterface) {
            $handler->addMiddleware($controller);
        }
        // Controllers can be implemented as a Payload RequestHandler
        if ($controller instanceof RequestHandlerInterface) {
            return $controller->handle($request);
        }



/*        if (isset($match['controller'])) {
            $config->setControllerName();
            $config->setSettingsExporterName($settingsFinder->getSettingsExporterName($config->getControllerName()));
        } else {
            $config->setControllerName('Horde_Core_Controller_NotFound');
        }
        // TODO: Move to some ControllerAuthHelper and check perms and admin
        if (!$registry->isAuthenticated()) {
            $auth = $injector->getInstance('Horde_Core_Factory_Auth')->create();

            // Default behaviour should be to authenticate if none given.
            // Older controllers expect this.
            if (!isset($match['HordeAuthType'])) {
                $match['HordeAuthType'] = 'DEFAULT';
            }
            // Keep unauthenticated
            if ($match['HordeAuthType'] == 'NONE') {
                return $config;
            }
            if ($match['HordeAuthType'] == 'DEFAULT') {
                // Try to authenticate, otherwise redirect to login page
                // Check for basic auth
                if (isset($_SERVER['PHP_AUTH_USER']) and isset($_SERVER['PHP_AUTH_PW'])) {
                    $res = $auth->authenticate($_SERVER['PHP_AUTH_USER'], ['password' => $_SERVER['PHP_AUTH_PW']]);
                    if ($res) {
                        return $config;
                    }
                }
                $registry->getServiceLink('login');
                Horde::url($registry->getInitialPage('horde'))->redirect();
            }
            // In API mode, either allow a request
            if ($match['HordeAuthType'] == 'BASIC') {
                if ($auth->authenticate($_SERVER['PHP_AUTH_USER'], ['password' => $_SERVER['PHP_AUTH_PW']])) {
                    return $config;
                }
                $config->setControllerName('Horde_Core_Controller_NotAuthorized');
                return $config;
            }
        }
*/

        return $handler->handle($request);
    }
}