<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde\Http\Server\RampageRequestHandler;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Injector\Injector;
use Horde\Routes\MatchResult;
use Horde_Controller;
use Horde_String;
use Psr\Http\Message\ResponseFactoryInterface;
use Horde\Exception\HordeException;
use Horde\Core\RuntimeRoutesProvider;

/**
 * AppRouter middleware
 *
 * Matches the request against the pre-loaded RuntimeRoutesProvider,
 * resolves the per-route middleware stack, and dispatches the controller.
 *
 * Sets Attributes:
 * - app
 * - matchResult
 * - route
 */
class AppRouter extends RampageRequestHandler implements MiddlewareInterface, RequestHandlerInterface
{
    public function __construct(
        private readonly RuntimeRoutesProvider $runtimeMapper,
        private readonly Injector $injector,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $result = $this->runtimeMapper->routematch($path);

        if ($result === null) {
            $responseFactory = new ResponseFactory();
            $streamFactory = new StreamFactory();
            return $responseFactory->createResponse(404)
                ->withBody($streamFactory->createStream('No route matched: ' . $path));
        }

        [$matchDict, $route] = $result;
        $app = $matchDict['app'];
        $routeName = $route->routeName ?? '';
        $matchResult = new MatchResult($matchDict, $route, $routeName);

        $request = $request->withAttribute('app', $app);
        $request = $request->withAttribute('matchResult', $matchResult);
        $request = $request->withAttribute('route', $matchDict);

        $this->injector->setInstance(MatchResult::class, $matchResult);

        // Resolve middleware stack
        // GroupMapper ensures stack is always present in matchDict.
        // Legacy fallback retained for routes loaded via old Mapper path.
        $stack = $matchDict['stack'] ?? DefaultStack::get();

        // DEBUG - Only log if HORDE_DEBUG_ROUTER is set
        if (getenv('HORDE_DEBUG_ROUTER')) {
            $varDir = '/tmp';
            $debugLog = $varDir . '/approuter-debug.log';
            file_put_contents($debugLog, "=== AppRouter Debug ===\n", FILE_APPEND);
            file_put_contents($debugLog, 'Time: ' . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'App: ' . $app . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'Route: ' . $routeName . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'Controller: ' . ($matchDict['controller'] ?? 'NONE') . "\n", FILE_APPEND);
            file_put_contents($debugLog, 'Using stack: ' . json_encode($stack) . "\n", FILE_APPEND);
            file_put_contents($debugLog, "=== End AppRouter Debug ===\n\n", FILE_APPEND);
        }

        foreach ($stack as $mw) {
            if ($mw === HordeCore::class) {
                // HordeCore is a takeover middleware — it does appInit, then
                // resolves the remaining stack from the legacy injector itself.
                // Pass the full stack and controller info via request attributes
                // so HordeCore knows what to dispatch downstream.
                $remaining = array_slice($stack, array_search(HordeCore::class, $stack) + 1);
                $request = $request->withAttribute('_hordecore_remaining_stack', $remaining);
                $request = $request->withAttribute('_hordecore_controller', $matchDict['controller'] ?? '');
                $handler->addMiddleware($this->injector->get($mw));
                return $handler->handle($request);
            }
            $handler->addMiddleware($this->injector->get($mw));
        }

        // Resolve controller (only reached for stacks without HordeCore)
        $controllerName = $matchDict['controller'] ?? '';
        $controller = null;
        if ($controllerName) {
            try {
                $controller = $this->injector->getInstance($controllerName);
            } catch (Exception $e) {
                throw new HordeException(
                    'Defined controller but could not create: ' . $controllerName . ' — ' . $e->getMessage(),
                    0,
                    $e
                );
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
            $handler->setPayloadHandler($controller);
        }

        return $handler->handle($request);
    }
}
