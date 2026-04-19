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

use Horde\Core\Middleware\AuthHttpBasic;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde_Auth_Base;
use Horde_Registry;

#[CoversClass(AuthHttpBasic::class)]
class AuthHttpBasicTest extends TestCase
{
    use SetUpTrait {
        setUp as protected traitSetUp;
    }

    protected function setUp(): void
    {
        $this->traitSetUp();
        // Replace stubs with mocks for expectations
        $this->authDriver = $this->createMock(Horde_Auth_Base::class);
        $this->registry = $this->createMock(Horde_Registry::class);
    }

    protected function getMiddleware()
    {
        return new AuthHttpBasic(
            $this->authDriver,
            $this->registry
        );
    }

    public function testNoAuthHeaderSetsNoAuthHeaderAttribute()
    {
        $username = 'existingUser';
        $middleware = $this->getMiddleware();

        // When no auth header, middleware calls getAuth() to get current session user
        $this->registry->expects($this->once())
            ->method('getAuth')
            ->willReturn($username);

        // authenticate() should NOT be called without header
        $this->authDriver->expects($this->never())
            ->method('authenticate');

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        // Should set NO_AUTH_HEADER attribute with existing user
        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');

        $this->assertSame($username, $noAuthHeader);
        $this->assertNull($authenticatedUser);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testValidCredentialsAuthenticates()
    {
        $username = 'testUser01';
        $password = 'testPw';
        $authString = base64_encode(sprintf('%s:%s', $username, $password));
        $middleware = $this->getMiddleware();

        // Mock expects authenticate() to be called with credentials
        $this->authDriver->expects($this->once())
            ->method('authenticate')
            ->with($username, ['password' => $password])
            ->willReturn(true);

        // getAuth() should NOT be called when auth header present
        $this->registry->expects($this->never())
            ->method('getAuth');

        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withHeader('Authorization', 'BASIC ' . $authString);
        $response = $middleware->process($request, $this->handler);

        // Should set HORDE_AUTHENTICATED_USER attribute
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');
        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');

        $this->assertSame($username, $authenticatedUser);
        $this->assertNull($noAuthHeader);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testInvalidCredentialsDoesNotAuthenticate()
    {
        $username = 'testUser01';
        $password = 'wrongPassword';
        $authString = base64_encode(sprintf('%s:%s', $username, $password));
        $middleware = $this->getMiddleware();

        // Mock expects authenticate() to be called and return false
        $this->authDriver->expects($this->once())
            ->method('authenticate')
            ->with($username, ['password' => $password])
            ->willReturn(false);

        // Registry should NOT be called when auth header present
        $this->registry->expects($this->never())
            ->method('getAuth');

        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withHeader('Authorization', 'BASIC ' . $authString);
        $response = $middleware->process($request, $this->handler);

        // Should NOT set authentication attributes
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');
        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');

        $this->assertNull($authenticatedUser);
        $this->assertNull($noAuthHeader);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testMalformedAuthHeaderIgnored()
    {
        $username = 'existingUser';
        $middleware = $this->getMiddleware();

        // Malformed header (no colon separator in decoded value)
        $malformed = base64_encode('usernameonly');

        // Should fall through without calling authenticate
        $this->authDriver->expects($this->never())
            ->method('authenticate');

        // Should still call getAuth since no valid auth happened
        $this->registry->expects($this->never())
            ->method('getAuth');

        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withHeader('Authorization', 'BASIC ' . $malformed);
        $response = $middleware->process($request, $this->handler);

        // No attributes should be set
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');
        $this->assertNull($authenticatedUser);
    }

    public function testNonBasicAuthHeaderIgnored()
    {
        $username = 'existingUser';
        $middleware = $this->getMiddleware();

        // Bearer token instead of Basic
        $this->authDriver->expects($this->never())
            ->method('authenticate');

        // Since header exists but not BASIC, getAuth is not called
        $this->registry->expects($this->never())
            ->method('getAuth');

        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withHeader('Authorization', 'Bearer some-jwt-token');
        $response = $middleware->process($request, $this->handler);

        // No attributes should be set
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');
        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');

        $this->assertNull($authenticatedUser);
        $this->assertNull($noAuthHeader);
    }

    public function testEmptyUsernameOrPasswordIgnored()
    {
        $middleware = $this->getMiddleware();

        // Empty username
        $authString1 = base64_encode(':password');
        // Empty password
        $authString2 = base64_encode('username:');

        // Both should call authenticate with empty values
        $this->authDriver->expects($this->exactly(2))
            ->method('authenticate')
            ->willReturn(false);

        // Registry should NOT be called when auth header present
        $this->registry->expects($this->never())
            ->method('getAuth');

        $request1 = $this->requestFactory->createServerRequest('GET', '/test')
            ->withHeader('Authorization', 'BASIC ' . $authString1);
        $middleware->process($request1, $this->handler);

        $request2 = $this->requestFactory->createServerRequest('GET', '/test')
            ->withHeader('Authorization', 'BASIC ' . $authString2);
        $middleware->process($request2, $this->handler);

        // Just verify no exceptions thrown
        $this->assertTrue(true);
    }
}
