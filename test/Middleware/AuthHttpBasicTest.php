<?php

/**
 * Copyright 2016-2022 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Test\Middleware;

use Horde\Core\Middleware\AuthHttpBasic;
use Horde\Test\TestCase;

class AuthHttpBasicTest extends TestCase
{
    use SetUpTrait;

    protected function getMiddleware()
    {
        return new AuthHttpBasic(
            $this->authDriver,
            $this->registry
        );
    }

    public function testNotAuthenticatedWithoutHeader()
    {
        $username = 'testUser01';
        $this->authDriver->method('authenticate')->willReturn(false);

        $this->registry->method('getAuth')->willReturn($username);

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $middleware = $this->getMiddleware();
        $middleware->process($request, $this->handler);

        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');

        $this->assertSame($username, $noAuthHeader);
        $this->assertNull($authenticatedUser);
    }

    public function testNotAuthenticatedWithHeader()
    {
        $username = 'testUser01';
        $password = 'testPw';
        $authString = base64_encode(sprintf('%s:%s', $username, $password));

        $this->authDriver->method('authenticate')->willReturn(false);

        $request = $this->requestFactory->createServerRequest('GET', '/test')->withHeader('Authorization', 'BASIC ' . $authString);
        $middleware = $this->getMiddleware();
        $middleware->process($request, $this->handler);

        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');

        $this->assertNull($noAuthHeader);
        $this->assertNull($authenticatedUser);
    }

    public function testAuthenticatedWithoutHeader()
    {
        $username = 'testUser01';
        $this->authDriver->method('authenticate')->willReturn(true);
        $this->registry->method('getAuth')->willReturn($username);

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $middleware = $this->getMiddleware();
        $response = $middleware->process($request, $this->handler);

        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');


        $this->assertSame($username, $noAuthHeader);
        $this->assertNull($authenticatedUser);
        $this->assertEquals($this->defaultPayloadResponse, $response);
    }

    public function testAuthenticatedWithHeader()
    {
        $username = 'testUser01';
        $password = 'testPw';
        $authString = base64_encode(sprintf('%s:%s', $username, $password));

        $this->authDriver->method('authenticate')->willReturn(true);
        $this->registry->method('getAuth')->willReturn($username);

        $request = $this->requestFactory->createServerRequest('GET', '/test')->withHeader('Authorization', 'BASIC ' . $authString);
        $middleware = $this->getMiddleware();
        $middleware->process($request, $this->handler);

        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');

        $this->assertNull($noAuthHeader);
        $this->assertSame($username, $authenticatedUser);
    }

    public function testAuthenticatedWitHeaderValueDoesNotStartWithBasic()
    {
        $username = 'testUser01';
        $password = 'testPw';
        $authString = base64_encode(sprintf('%s:%s', $username, $password));
        $this->authDriver->method('authenticate')->willReturn(true);
        $this->registry->method('getAuth')->willReturn($username);

        $request = $this->requestFactory->createServerRequest('GET', '/test')->withHeader('Authorization', 'BASIS ' . $authString);

        $middleware = $this->getMiddleware();
        $middleware->process($request, $this->handler);

        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');

        $this->assertNull($noAuthHeader);
        $this->assertNull($authenticatedUser);
    }

    public function testAuthenticatedWithHeaderValueAuthInvalidFormat()
    {
        $username = 'testUser01';
        $password = 'testPw';
        $authString = base64_encode(sprintf('%s_%s', $username, $password));
        $this->authDriver->method('authenticate')->willReturn(true);

        $request = $this->requestFactory->createServerRequest('GET', '/test')->withHeader('Authorization', 'BASIC ' . $authString);

        $middleware = $this->getMiddleware();
        $middleware->process($request, $this->handler);

        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');

        $this->assertNull($noAuthHeader);
        $this->assertNull($authenticatedUser);
    }

    public function testAuthenticatedWithHeaderValueAuthNotBase64()
    {
        $username = 'testUser01';
        $password = 'testPw';
        $authString = sprintf('%s:%s', $username, $password);
        $this->authDriver->method('authenticate')->willReturn(true);

        $request = $this->requestFactory->createServerRequest('GET', '/test')->withHeader('Authorization', 'BASIC ' . $authString);

        $middleware = $this->getMiddleware();
        $middleware->process($request, $this->handler);

        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');

        $this->assertNull($noAuthHeader);
        $this->assertNull($authenticatedUser);
    }

    public function testAuthenticatedChecksHeadersUntilValidAuth()
    {
        $username1 = 'testUser01';
        $username2 = 'testUser02';
        $password = 'testPw';
        $invalidAuthString = 'WRONG';
        $validAuthString1 = base64_encode(sprintf('%s:%s', $username1, $password));
        $validAuthString2 = base64_encode(sprintf('%s:%s', $username2, $password));
        $this->authDriver->method('authenticate')->willReturn(true);

        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withAddedHeader('Authorization', 'BASIC ' . $invalidAuthString)
            ->withAddedHeader('Authorization', 'BASIC ' . $validAuthString1)
            ->withAddedHeader('Authorization', 'BASIC ' . $validAuthString2);

        $middleware = $this->getMiddleware();
        $middleware->process($request, $this->handler);

        $noAuthHeader = $this->recentlyHandledRequest->getAttribute('NO_AUTH_HEADER');
        $authenticatedUser = $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER');

        $this->assertNull($noAuthHeader);
        $this->assertSame($username1, $authenticatedUser);
    }
}
