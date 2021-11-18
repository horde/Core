<?php
/**
 * Copyright 2016-2021 Horde LLC (http://www.horde.org/)
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
    use SetUpTrait;

    protected function getMiddleware()
    {
        return new AuthHordeSession($this->registry);
    }

    public function testIsAuthenticated()
    {
        $username = 'testuser01';
        $middleware = $this->getMiddleware();
        $this->registry->method('isAuthenticated')->willReturn(true);
        $this->registry->method('getAuth')->willReturn($username);
        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        $authUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');
        $guestUser = $this->recentlyHandledRequest->getAttribute('HORDE_GUEST');
        // assert that $authUser and $guestUser have the correct values
        $this->assertEquals($username, $authUser);
        $this->assertNull($guestUser);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIsNotAuthenticated()
    {
        $username = 'testuser01';
        $middleware = $this->getMiddleware();
        $this->registry->method('isAuthenticated')->willReturn(false);
        $this->registry->method('getAuth')->willReturn($username);
        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        $authUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');
        $guestUser = $this->recentlyHandledRequest->getAttribute('HORDE_GUEST');
        // assert that $authUser and $guestUser have the correct values
        $this->assertTrue($guestUser);
        $this->assertNull($authUser);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
