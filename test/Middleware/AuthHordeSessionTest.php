<?php

/**
 * Copyright 2016-2021 Horde LLC (http://www.horde.org/licenses/lgpl21.
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Test\Middleware;

use Horde\Core\Middleware\AuthHordeSession;
use Horde\Test\TestCase;
use Horde_Session;
use Horde_Exception;
use Horde_Registry;

class AuthHordeSessionTest extends TestCase
{
    use SetUpTrait {
        setUp as protected traitSetUp;
    }

    protected function setUp(): void
    {
        // Call trait setUp first
        $this->traitSetUp();
        // Then replace stub with mock for expectations
        $this->registry = $this->createMock(Horde_Registry::class);
    }

    protected function getMiddleware()
    {
        return new AuthHordeSession($this->registry);
    }

    public function testIsAuthenticated()
    {
        $username = 'testuser01';
        $middleware = $this->getMiddleware();

        // Mock expects isAuthenticated() to be called and return true
        $this->registry->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(true);

        // Mock expects getAuth() to be called and return username
        $this->registry->expects($this->once())
            ->method('getAuth')
            ->willReturn($username);

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        // Verify attributes set on request passed to handler
        $authUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');
        $guestUser = $this->recentlyHandledRequest->getAttribute('HORDE_GUEST');

        // Should set authenticated user attribute
        $this->assertEquals($username, $authUser);
        // Should NOT set guest attribute
        $this->assertNull($guestUser);

        // Should pass through to handler
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIsNotAuthenticated()
    {
        $middleware = $this->getMiddleware();

        // Mock expects isAuthenticated() to be called and return false
        $this->registry->expects($this->once())
            ->method('isAuthenticated')
            ->willReturn(false);

        // getAuth() should NOT be called when not authenticated
        $this->registry->expects($this->never())
            ->method('getAuth');

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        // Verify attributes set on request passed to handler
        $authUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');
        $guestUser = $this->recentlyHandledRequest->getAttribute('HORDE_GUEST');

        // Should NOT set authenticated user
        $this->assertNull($authUser);
        // Should set guest flag
        $this->assertTrue($guestUser);

        // Should still pass through to handler
        $this->assertEquals(200, $response->getStatusCode());
    }
}
