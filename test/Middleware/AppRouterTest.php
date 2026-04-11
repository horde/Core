<?php

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

declare(strict_types=1);

namespace Horde\Core\Test\Middleware;

use Horde\Core\Middleware\AppRouter;
use Horde\Core\Middleware\AuthHordeSession;
use Horde\Core\Middleware\RedirectToLogin;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\Server\RampageRequestHandler;
use Horde\Http\StreamFactory;
use Horde\Test\TestCase;
use Horde_Injector;
use Horde_Registry;
use Horde\Routes\Mapper;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Exception;

/**
 * Unit tests for AppRouter middleware
 *
 * Tests routing functionality including:
 * - Route matching from URI
 * - Controller resolution
 * - Middleware stack configuration
 * - Default vs custom middleware stacks
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @package  Core
 */
#[CoversClass(AppRouter::class)]
class AppRouterTest extends TestCase
{
    private RequestFactory $requestFactory;
    private ResponseFactory $responseFactory;
    private StreamFactory $streamFactory;
    private Horde_Registry $registry;
    private Mapper $router;
    private Horde_Injector $injector;
    private AppRouter $appRouter;
    private RampageRequestHandler $handler;

    protected function setUp(): void
    {
        // Define HORDE_CONFIG_BASE for tests that need ConfigLoader
        if (!defined('HORDE_CONFIG_BASE')) {
            define('HORDE_CONFIG_BASE', sys_get_temp_dir() . '/horde-test-config');
        }

        $this->requestFactory = new RequestFactory();
        $this->responseFactory = new ResponseFactory();
        $this->streamFactory = new StreamFactory();

        // Mock registry
        $this->registry = $this->createMock(Horde_Registry::class);

        // Real router
        $this->router = new Mapper();

        // Mock injector - configured per test
        $this->injector = $this->createMock(Horde_Injector::class);

        $this->appRouter = new AppRouter($this->registry, $this->router, $this->injector);
    }

    /**
     * Create a test middleware that adds an attribute to track execution
     */
    private function createTestMiddleware(string $name): MiddlewareInterface
    {
        return new class ($name) implements MiddlewareInterface {
            public function __construct(private string $name) {}

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $executed = $request->getAttribute('middlewares_executed', []);
                $executed[] = $this->name;
                $request = $request->withAttribute('middlewares_executed', $executed);
                return $handler->handle($request);
            }
        };
    }

    /**
     * Test basic route matching with controller
     */
    public function testMatchRouteWithController(): void
    {
        // Create temporary routes file
        $tempDir = sys_get_temp_dir() . '/horde-test-' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/config');
        $routesFile = $tempDir . '/config/routes.php';

        file_put_contents(
            $routesFile,
            <<<'PHP'
                <?php
                $mapper->connect('test-route', '/test', ['controller' => 'TestController']);
                PHP
        );

        // Setup registry mock
        $this->registry->expects($this->once())
            ->method('get')
            ->with('fileroot', 'testapp')
            ->willReturn($tempDir);

        $this->registry->expects($this->once())
            ->method('pushApp')
            ->with('testapp');

        // Setup injector
        $this->injector->method('get')
            ->willReturnCallback(function ($key) {
                if ($key === ResponseFactoryInterface::class) {
                    return $this->responseFactory;
                }
                if ($key === StreamFactoryInterface::class) {
                    return $this->streamFactory;
                }
                if ($key === AuthHordeSession::class) {
                    return $this->createTestMiddleware('AuthHordeSession');
                }
                if ($key === RedirectToLogin::class) {
                    return $this->createTestMiddleware('RedirectToLogin');
                }
                throw new Exception("Injector: Unknown key: $key");
            });

        // Setup injector to return a mock controller
        $mockController = $this->createMock(RequestHandlerInterface::class);
        $mockController->expects($this->once())->method('handle')
            ->willReturn($this->responseFactory->createResponse(200));

        $this->injector->expects($this->once())->method('getInstance')
            ->with('TestController')
            ->willReturn($mockController);

        // Create request
        $request = $this->requestFactory->createServerRequest('GET', 'http://example.com/testapp/test')
            ->withAttribute('app', 'testapp')
            ->withAttribute('routerPrefix', '/testapp');

        // Create handler
        $handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            []
        );

        // Process
        $response = $this->appRouter->process($request, $handler);

        $this->assertEquals(200, $response->getStatusCode());

        // Cleanup
        unlink($routesFile);
        rmdir($tempDir . '/config');
        rmdir($tempDir);
    }

    /**
     * Test route matching with default middleware stack
     */
    public function testRouteWithDefaultMiddlewareStack(): void
    {
        // Create temporary routes file
        $tempDir = sys_get_temp_dir() . '/horde-test-' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/config');
        $routesFile = $tempDir . '/config/routes.php';

        // Route without explicit stack = default stack
        file_put_contents(
            $routesFile,
            <<<'PHP'
                <?php
                $mapper->connect('default-stack', '/default', ['controller' => 'DefaultController']);
                PHP
        );

        $this->registry->expects($this->once())->method('get')
            ->with('fileroot', 'testapp')
            ->willReturn($tempDir);

        $this->registry->method('pushApp');

        // Setup injector to provide default middlewares
        $this->injector->method('get')
            ->willReturnCallback(function ($key) {
                if ($key === ResponseFactoryInterface::class) {
                    return $this->responseFactory;
                }
                if ($key === StreamFactoryInterface::class) {
                    return $this->streamFactory;
                }
                if ($key === AuthHordeSession::class) {
                    return $this->createTestMiddleware('AuthHordeSession');
                }
                if ($key === RedirectToLogin::class) {
                    return $this->createTestMiddleware('RedirectToLogin');
                }
                throw new Exception("Injector: Unknown key: $key");
            });

        // Mock controller that tracks middleware execution
        $mockController = $this->createMock(RequestHandlerInterface::class);
        $mockController->expects($this->once())->method('handle')
            ->willReturnCallback(function ($request) {
                $middlewares = $request->getAttribute('middlewares_executed', []);
                // Verify default middlewares ran
                $this->assertContains('AuthHordeSession', $middlewares);
                $this->assertContains('RedirectToLogin', $middlewares);
                return $this->responseFactory->createResponse(200);
            });

        $this->injector->expects($this->once())->method('getInstance')
            ->with('DefaultController')
            ->willReturn($mockController);

        $request = $this->requestFactory->createServerRequest('GET', 'http://example.com/testapp/default')
            ->withAttribute('app', 'testapp')
            ->withAttribute('routerPrefix', '/testapp');

        $handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            []
        );

        $response = $this->appRouter->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());

        // Cleanup
        unlink($routesFile);
        rmdir($tempDir . '/config');
        rmdir($tempDir);
    }

    /**
     * Test route with empty stack (bypasses HordeCore default middleware)
     */
    public function testRouteWithEmptyStackBypassesDefaultMiddleware(): void
    {
        $tempDir = sys_get_temp_dir() . '/horde-test-' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/config');
        $routesFile = $tempDir . '/config/routes.php';

        // Explicit empty stack = NO middleware
        file_put_contents(
            $routesFile,
            <<<'PHP'
                <?php
                $mapper->connect('no-middleware', '/public', [
                    'controller' => 'PublicController',
                    'stack' => []
                ]);
                PHP
        );

        $this->registry->expects($this->once())->method('get')
            ->with('fileroot', 'testapp')
            ->willReturn($tempDir);

        $this->registry->method('pushApp');

        // Setup injector to NOT provide middlewares for empty stack test
        // The injector should only be called for controllers, not middlewares when stack is empty
        $this->injector->method('get')
            ->willReturnCallback(function ($key) {
                if ($key === ResponseFactoryInterface::class) {
                    return $this->responseFactory;
                }
                if ($key === StreamFactoryInterface::class) {
                    return $this->streamFactory;
                }
                // If middlewares are requested, something is wrong with empty stack
                if ($key === AuthHordeSession::class || $key === RedirectToLogin::class) {
                    $this->fail("Middleware {$key} should not be requested with empty stack");
                }
                throw new Exception("Injector: Unknown key: $key");
            });
        $mockController = $this->createMock(RequestHandlerInterface::class);
        $mockController->expects($this->once())->method('handle')
            ->willReturnCallback(function ($request) {
                $middlewares = $request->getAttribute('middlewares_executed', []);
                // Empty stack = no default middlewares
                $this->assertEmpty($middlewares, 'No middlewares should execute with empty stack');
                return $this->responseFactory->createResponse(200);
            });

        $this->injector->expects($this->once())->method('getInstance')
            ->with('PublicController')
            ->willReturn($mockController);

        $request = $this->requestFactory->createServerRequest('GET', 'http://example.com/testapp/public')
            ->withAttribute('app', 'testapp')
            ->withAttribute('routerPrefix', '/testapp');

        $handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            []
        );

        $response = $this->appRouter->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());

        // Cleanup
        unlink($routesFile);
        rmdir($tempDir . '/config');
        rmdir($tempDir);
    }

    /**
     * Test route with HordeAuthType=NONE (legacy way to bypass middleware)
     */
    public function testRouteWithHordeAuthTypeNoneBypassesMiddleware(): void
    {
        $tempDir = sys_get_temp_dir() . '/horde-test-' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/config');
        $routesFile = $tempDir . '/config/routes.php';

        // HordeAuthType=NONE implies empty stack
        file_put_contents(
            $routesFile,
            <<<'PHP'
                <?php
                $mapper->connect('auth-none', '/login', [
                    'controller' => 'LoginController',
                    'HordeAuthType' => 'NONE'
                ]);
                PHP
        );

        $this->registry->expects($this->once())->method('get')
            ->with('fileroot', 'testapp')
            ->willReturn($tempDir);

        $this->registry->method('pushApp');

        // Setup injector - should NOT provide middlewares for HordeAuthType=NONE
        $this->injector->method('get')
            ->willReturnCallback(function ($key) {
                if ($key === ResponseFactoryInterface::class) {
                    return $this->responseFactory;
                }
                if ($key === StreamFactoryInterface::class) {
                    return $this->streamFactory;
                }
                // Middlewares should not be requested
                if ($key === AuthHordeSession::class || $key === RedirectToLogin::class) {
                    $this->fail("Middleware {$key} should not be requested with HordeAuthType=NONE");
                }
                throw new Exception("Injector: Unknown key: $key");
            });

        $mockController = $this->createMock(RequestHandlerInterface::class);
        $mockController->expects($this->once())->method('handle')
            ->willReturnCallback(function ($request) {
                $middlewares = $request->getAttribute('middlewares_executed', []);
                $this->assertEmpty($middlewares, 'HordeAuthType=NONE should bypass middlewares');
                return $this->responseFactory->createResponse(200);
            });

        $this->injector->expects($this->once())->method('getInstance')
            ->with('LoginController')
            ->willReturn($mockController);

        $request = $this->requestFactory->createServerRequest('GET', 'http://example.com/testapp/login')
            ->withAttribute('app', 'testapp')
            ->withAttribute('routerPrefix', '/testapp');

        $handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            []
        );

        $response = $this->appRouter->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());

        // Cleanup
        unlink($routesFile);
        rmdir($tempDir . '/config');
        rmdir($tempDir);
    }

    /**
     * Test missing app attribute throws exception
     */
    public function testMissingAppAttributeThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing Attribute: 'app'");

        // Injector.has() is called early to check for config loaders
        $this->injector->expects($this->atLeastOnce())->method('has')->willReturn(true);

        // Registry should not be called when attribute validation fails early
        $this->registry->expects($this->never())->method($this->anything());

        $request = $this->requestFactory->createServerRequest('GET', 'http://example.com/test')
            ->withAttribute('routerPrefix', '/test');

        $handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            []
        );

        $this->appRouter->process($request, $handler);
    }

    /**
     * Test missing routerPrefix attribute throws exception
     */
    public function testMissingRouterPrefixThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Missing Attribute: 'routerPrefix'");

        // Injector.has() is called early to check for config loaders
        $this->injector->expects($this->atLeastOnce())->method('has')->willReturn(true);

        // Registry should not be called when attribute validation fails early
        $this->registry->expects($this->never())->method($this->anything());

        $request = $this->requestFactory->createServerRequest('GET', 'http://example.com/test')
            ->withAttribute('app', 'testapp');

        $handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            []
        );

        $this->appRouter->process($request, $handler);
    }

    /**
     * Test route match is added as request attribute
     */
    public function testRouteMatchAddedAsAttribute(): void
    {
        $tempDir = sys_get_temp_dir() . '/horde-test-' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/config');
        $routesFile = $tempDir . '/config/routes.php';

        file_put_contents(
            $routesFile,
            <<<'PHP'
                <?php
                $mapper->connect('test-params', '/item/:id', [
                    'controller' => 'ItemController',
                    'stack' => []
                ]);
                PHP
        );

        $this->registry->expects($this->once())->method('get')
            ->with('fileroot', 'testapp')
            ->willReturn($tempDir);

        $this->registry->method('pushApp');

        // Setup injector for empty stack test
        $this->injector->method('get')
            ->willReturnCallback(function ($key) {
                if ($key === ResponseFactoryInterface::class) {
                    return $this->responseFactory;
                }
                if ($key === StreamFactoryInterface::class) {
                    return $this->streamFactory;
                }
                throw new Exception("Injector: Unknown key: $key");
            });

        $mockController = $this->createMock(RequestHandlerInterface::class);
        $mockController->expects($this->once())->method('handle')
            ->willReturnCallback(function ($request) {
                $route = $request->getAttribute('route');
                $this->assertIsArray($route);
                $this->assertEquals('123', $route['id']);
                $this->assertEquals('ItemController', $route['controller']);
                return $this->responseFactory->createResponse(200);
            });

        $this->injector->expects($this->once())->method('getInstance')
            ->with('ItemController')
            ->willReturn($mockController);

        $request = $this->requestFactory->createServerRequest('GET', 'http://example.com/testapp/item/123')
            ->withAttribute('app', 'testapp')
            ->withAttribute('routerPrefix', '/testapp');

        $handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            []
        );

        $response = $this->appRouter->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());

        // Cleanup
        unlink($routesFile);
        rmdir($tempDir . '/config');
        rmdir($tempDir);
    }
}
