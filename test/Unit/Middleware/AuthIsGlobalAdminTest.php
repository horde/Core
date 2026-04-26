<?php

declare(strict_types=1);

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

namespace Horde\Core\Test\Unit\Middleware;

use Horde\Core\Middleware\AuthIsGlobalAdmin;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AuthIsGlobalAdmin::class)]
class AuthIsGlobalAdminTest extends TestCase
{
    use SetUpTrait;

    public function testIsAdmin()
    {
        $middleware = new AuthIsGlobalAdmin(['admin@example.com', 'root@example.com']);

        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withAttribute('HORDE_AUTHENTICATED_USER', 'admin@example.com');
        $response = $middleware->process($request, $this->handler);

        $this->assertTrue($this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testIsNotAdmin()
    {
        $middleware = new AuthIsGlobalAdmin(['admin@example.com']);

        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withAttribute('HORDE_AUTHENTICATED_USER', 'user@example.com');
        $response = $middleware->process($request, $this->handler);

        $this->assertNull($this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUserIsNotAuthenticated()
    {
        $middleware = new AuthIsGlobalAdmin(['admin@example.com']);

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        $this->assertNull($this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testEmptyAdminList()
    {
        $middleware = new AuthIsGlobalAdmin([]);

        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withAttribute('HORDE_AUTHENTICATED_USER', 'admin@example.com');
        $response = $middleware->process($request, $this->handler);

        $this->assertNull($this->recentlyHandledRequest->getAttribute('HORDE_GLOBAL_ADMIN'));
        $this->assertEquals(200, $response->getStatusCode());
    }
}
