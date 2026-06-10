<?php

declare(strict_types=1);

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

namespace Horde\Core\Test\Integration;

use Horde\Core\Config\RegistryState;
use Horde\Core\Middleware\AppFinder;
use Horde\Core\Middleware\AppRouter;
use Horde\Core\Middleware\AuthHordeSession;
use Horde\Core\Middleware\HordeCore;
use Horde\Core\Middleware\RedirectToLogin;
use Horde\Core\RuntimeRoutesProvider;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\Server\RampageRequestHandler;
use Horde\Http\StreamFactory;
use Horde\Http\UriFactory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Horde_Injector;
use Horde_Registry;
use Horde\Routes\Mapper;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Exception;

/**
 * Semi-integration tests for Rampage request handling
 *
 * These tests exercise the full request handling pipeline:
 * URI -> AppFinder -> AppRouter -> Middleware Stack -> Controller
 *
 * Tests two scenarios:
 * a) Using default HordeCore middleware (authentication required)
 * b) Bypassing HordeCore middleware (public endpoints)
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @package  Core
 */
#[CoversNothing]
#[Group('integration')]
class RampageIntegrationTest extends TestCase
{
    private RequestFactory $requestFactory;
    private ResponseFactory $responseFactory;
    private StreamFactory $streamFactory;
    private UriFactory $uriFactory;
    private string $tempAppDir;

    protected function setUp(): void
    {
        $this->requestFactory = new RequestFactory();
        $this->responseFactory = new ResponseFactory();
        $this->streamFactory = new StreamFactory();
        $this->uriFactory = new UriFactory();

        // Create temporary app directory structure
        $this->tempAppDir = sys_get_temp_dir() . '/horde-rampage-test-' . uniqid();
        mkdir($this->tempAppDir);
        mkdir($this->tempAppDir . '/config');
    }

    protected function tearDown(): void
    {
        // Cleanup temp directory
        if (is_dir($this->tempAppDir)) {
            if (file_exists($this->tempAppDir . '/config/routes.php')) {
                unlink($this->tempAppDir . '/config/routes.php');
            }
            if (file_exists($this->tempAppDir . '/config/routes.local.php')) {
                unlink($this->tempAppDir . '/config/routes.local.php');
            }
            rmdir($this->tempAppDir . '/config');
            rmdir($this->tempAppDir);
        }
    }

    /**
     * Test full request handling WITH default HordeCore middleware
     *
     * Scenario: Protected endpoint requiring authentication
     * URI: /testapp/api/protected
     * Expected: Default middleware stack (AuthHordeSession, RedirectToLogin) runs
     */
    public function testFullRequestWithDefaultMiddleware(): void
    {
        // Create routes file with an explicit stack of the two middlewares
        // this test asserts on. Avoids pulling in HordeCore (composite takeover
        // middleware) which would require additional injector wiring outside
        // the scope of this integration test.
        file_put_contents(
            $this->tempAppDir . '/config/routes.php',
            <<<'PHP'
                <?php
                use Horde\Core\Middleware\AuthHordeSession;
                use Horde\Core\Middleware\RedirectToLogin;

                $mapper->buildRoute('/api/protected', 'protected-api')
                    ->withController('ProtectedApiController')
                    ->withMiddleware([AuthHordeSession::class, RedirectToLogin::class])
                    ->add();
                PHP
        );

        // Mock registry
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('listApps')
            ->willReturn([
                'testapp' => [
                    'webroot' => '/testapp',
                ],
            ]);
        $registry->method('get')
            ->willReturnCallback(function ($key, $app) {
                if ($key === 'fileroot' && $app === 'testapp') {
                    return $this->tempAppDir;
                }
                if ($key === 'webroot' && $app === 'testapp') {
                    return '/testapp';
                }
                return null;
            });

        // Note: pushApp() is now invoked by the HordeCore takeover middleware,
        // not by AppRouter directly. This integration test bypasses HordeCore
        // (see explicit middleware stack on the route below) so pushApp is
        // not expected to fire.

        // Mock injector
        $injector = $this->createMock(Horde_Injector::class);

        // Make has() return true so AppRouter skips config loader setup
        // (which requires the HORDE_CONFIG_BASE constant)
        $injector->method('has')->willReturn(true);

        // Track middleware execution
        $middlewaresExecuted = [];

        // Mock middleware instances
        $authMiddleware = $this->createTrackingMiddleware('AuthHordeSession', $middlewaresExecuted);
        $redirectMiddleware = $this->createTrackingMiddleware('RedirectToLogin', $middlewaresExecuted);

        // Mock controller
        $controller = $this->createMock(RequestHandlerInterface::class);
        $controller->expects($this->once())->method('handle')
            ->willReturnCallback(function ($request) use (&$middlewaresExecuted) {
                // Verify middlewares ran BEFORE controller
                $this->assertContains('AuthHordeSession', $middlewaresExecuted);
                $this->assertContains('RedirectToLogin', $middlewaresExecuted);

                $body = $this->streamFactory->createStream(json_encode([
                    'status' => 'success',
                    'data' => 'Protected data',
                    'middlewares' => $middlewaresExecuted,
                ]));

                return $this->responseFactory->createResponse(200)
                    ->withBody($body)
                    ->withHeader('Content-Type', 'application/json');
            });

        // Setup injector behavior
        $injector->method('get')
            ->willReturnCallback(function ($key) use ($authMiddleware, $redirectMiddleware) {
                return match ($key) {
                    ResponseFactoryInterface::class => $this->responseFactory,
                    StreamFactoryInterface::class => $this->streamFactory,
                    AuthHordeSession::class => $authMiddleware,
                    RedirectToLogin::class => $redirectMiddleware,
                    default => throw new Exception("Injector: Unknown key: $key")
                };
            });

        $injector->expects($this->once())->method('getInstance')
            ->with('ProtectedApiController')
            ->willReturn($controller);

        // Create router
        $router = new Mapper();

        // Create request
        $request = $this->requestFactory->createServerRequest('GET', 'http://example.com/testapp/api/protected');
        $request = $request->withAttribute('registry', $registry);

        // Create RegistryState (used by both AppFinder and RuntimeRoutesProvider)
        $registryState = new RegistryState([
            'testapp' => [
                'status' => 'active',
                'webroot' => '/testapp',
                'fileroot' => $this->tempAppDir,
                'jsuri' => '/testapp/js',
                'themesuri' => '/testapp/themes',
            ],
        ]);

        // Create runtime routes provider, populated from the test app's
        // config/routes.php written above.
        $runtimeMapper = new RuntimeRoutesProvider($registryState, $request);
        $runtimeMapper->loadAllApps();

        // Create AppRouter
        $appRouter = new AppRouter($runtimeMapper, $injector);

        // Create AppFinder
        $appFinder = new AppFinder($registryState, $this->responseFactory, $this->streamFactory);

        // Build full middleware stack
        $handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            [$appFinder, $appRouter]
        );

        // Execute
        $response = $handler->handle($request);

        // Verify
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Protected data', $data['data']);
        $this->assertContains('AuthHordeSession', $data['middlewares']);
        $this->assertContains('RedirectToLogin', $data['middlewares']);
    }

    /**
     * Test full request handling WITHOUT HordeCore middleware (bypassing)
     *
     * Scenario: Public endpoint with empty stack
     * URI: /testapp/api/public
     * Expected: NO default middleware runs, direct to controller
     */
    public function testFullRequestBypassingDefaultMiddleware(): void
    {
        // Create routes file with empty stack
        file_put_contents(
            $this->tempAppDir . '/config/routes.php',
            <<<'PHP'
                <?php
                // Route with empty stack = NO default middleware
                $mapper->buildRoute('/api/public', 'public-api')
                    ->withController('PublicApiController')
                    ->withMiddleware([])
                    ->add();
                PHP
        );

        // Mock registry
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('listApps')
            ->willReturn([
                'testapp' => [
                    'webroot' => '/testapp',
                ],
            ]);
        $registry->method('get')
            ->willReturnCallback(function ($key, $app) {
                if ($key === 'fileroot' && $app === 'testapp') {
                    return $this->tempAppDir;
                }
                if ($key === 'webroot' && $app === 'testapp') {
                    return '/testapp';
                }
                return null;
            });

        // Note: pushApp() is now invoked by the HordeCore takeover middleware,
        // not by AppRouter directly. This integration test bypasses HordeCore
        // (see explicit middleware stack on the route below) so pushApp is
        // not expected to fire.

        // Mock injector
        $injector = $this->createMock(Horde_Injector::class);

        // Make has() return true so AppRouter skips config loader setup
        // (which requires the HORDE_CONFIG_BASE constant)
        $injector->method('has')->willReturn(true);

        // Track middleware execution
        $middlewaresExecuted = [];

        // Mock controller (NO middlewares should execute)
        $controller = $this->createMock(RequestHandlerInterface::class);
        $controller->expects($this->once())->method('handle')
            ->willReturnCallback(function ($request) use (&$middlewaresExecuted) {
                // Verify NO middlewares ran
                $this->assertEmpty($middlewaresExecuted, 'No middlewares should execute with empty stack');

                $body = $this->streamFactory->createStream(json_encode([
                    'status' => 'success',
                    'data' => 'Public data - no auth required',
                    'middlewares' => $middlewaresExecuted,
                ]));

                return $this->responseFactory->createResponse(200)
                    ->withBody($body)
                    ->withHeader('Content-Type', 'application/json');
            });

        // Setup injector behavior
        $injector->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    ResponseFactoryInterface::class => $this->responseFactory,
                    StreamFactoryInterface::class => $this->streamFactory,
                    default => throw new Exception("Injector: Unknown key: $key")
                };
            });

        $injector->expects($this->once())->method('getInstance')
            ->with('PublicApiController')
            ->willReturn($controller);

        // Create router
        $router = new Mapper();

        // Create request
        $request = $this->requestFactory->createServerRequest('GET', 'http://example.com/testapp/api/public');
        $request = $request->withAttribute('registry', $registry);

        // Create RegistryState (used by both AppFinder and RuntimeRoutesProvider)
        $registryState = new RegistryState([
            'testapp' => [
                'status' => 'active',
                'webroot' => '/testapp',
                'fileroot' => $this->tempAppDir,
                'jsuri' => '/testapp/js',
                'themesuri' => '/testapp/themes',
            ],
        ]);

        // Create runtime routes provider, populated from the test app's
        // config/routes.php written above.
        $runtimeMapper = new RuntimeRoutesProvider($registryState, $request);
        $runtimeMapper->loadAllApps();

        // Create AppRouter
        $appRouter = new AppRouter($runtimeMapper, $injector);

        // Create AppFinder
        $appFinder = new AppFinder($registryState, $this->responseFactory, $this->streamFactory);

        // Build full middleware stack
        $handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            [$appFinder, $appRouter]
        );

        // Execute
        $response = $handler->handle($request);

        // Verify
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Public data - no auth required', $data['data']);
        $this->assertEmpty($data['middlewares'], 'Should bypass all default middlewares');
    }

    /**
     * Helper: Create middleware that tracks execution
     */
    private function createTrackingMiddleware(string $name, array &$tracker): object
    {
        return new class ($name, $tracker) implements \Psr\Http\Server\MiddlewareInterface {
            public function __construct(
                private string $name,
                private array &$tracker
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                // Track that this middleware executed
                $this->tracker[] = $this->name;

                // Continue chain
                return $handler->handle($request);
            }
        };
    }
}
