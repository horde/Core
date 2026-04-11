<?php

/**
 * Copyright 2016-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Test\Middleware;

use Horde\Core\Middleware\AuthIsGlobalAdmin;
use Horde\Test\TestCase;
use Horde_Registry;

/**
 * @coversNothing
 */
class AuthIsGlobalAdminTest extends TestCase
{
    use SetUpTrait {
        setUp as protected traitSetUp;
    }

    protected function setUp(): void
    {
        $this->traitSetUp();
        // Replace stub with mock since we need expectations
        $this->registry = $this->createMock(Horde_Registry::class);
    }

    protected function getMiddleware()
    {
        return new AuthIsGlobalAdmin($this->registry);
    }

    public function testIsAdmin()
    {
        $middleware = $this->getMiddleware();

        // Mock expects both isAuthenticated() and isAdmin() to be called
        $this->registry->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $this->registry->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        // Verify HORDE_GLOBAL_ADMIN attribute is set to true
        $authAdminUser = $this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN');
        $this->assertTrue($authAdminUser);

        // Verify request passed through to handler
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($this->recentlyHandledRequest);
    }

    public function testIsNotAdmin()
    {
        $middleware = $this->getMiddleware();

        // User is authenticated but not admin
        $this->registry->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $this->registry->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        // Verify HORDE_GLOBAL_ADMIN attribute is NOT set (null)
        $authAdminUser = $this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN');
        $this->assertNull($authAdminUser);

        // Verify request still passed through
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUserIsNotAuthenticated()
    {
        $middleware = $this->getMiddleware();

        // Mock expects isAuthenticated() but NOT isAdmin() since short-circuit
        $this->registry->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(false);

        // isAdmin() should NOT be called when not authenticated (short-circuit)
        $this->registry->expects($this->never())
            ->method('isAdmin');

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        // Verify HORDE_GLOBAL_ADMIN attribute is NOT set
        $authAdminUser = $this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN');
        $this->assertNull($authAdminUser);

        // Verify request still passed through
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testAdminWhenBothTrue()
    {
        $middleware = $this->getMiddleware();

        // Edge case: explicitly verify both must be true
        $this->registry->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $this->registry->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $request = $this->requestFactory->createServerRequest('GET', '/admin/panel');
        $response = $middleware->process($request, $this->handler);

        // Both checks passed, so admin attribute should be set
        $this->assertTrue($this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN'));
    }
}
