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

use Horde\Core\Middleware\DemandSessionToken;
use Horde\Test\TestCase;
use Horde_Session;
use Horde_Exception;

class DemandSessionTokenTest extends TestCase
{
    use SetUpTrait {
        setUp as protected traitSetUp;
    }

    protected function setUp(): void
    {
        // Call trait setUp first
        $this->traitSetUp();
        // Then replace stub with mock for expectations
        $this->session = $this->createMock(Horde_Session::class);
    }

    protected function getMiddleware()
    {
        return new DemandSessionToken(
            $this->responseFactory,
            $this->streamFactory,
            $this->session
        );
    }

    public function testSessionTokenMissing()
    {
        $middleware = $this->getMiddleware();

        // Mock expects checkToken() to be called once and throw exception
        $this->session->expects($this->once())
            ->method('checkToken')
            ->willThrowException(new Horde_Exception('Invalid token'));

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        // Should return 403 Forbidden
        $this->assertEquals(403, $response->getStatusCode());

        // Check reason phrase contains meaningful message
        $this->assertStringContainsString('Horde-Session-Token', $response->getReasonPhrase());
    }

    public function testSessionTokenCorrect()
    {
        $middleware = $this->getMiddleware();

        // Mock expects checkToken() to be called once and succeed (no exception)
        $this->session->expects($this->once())
            ->method('checkToken')
            ->willReturn(true);

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        // Should pass through to handler
        $this->assertEquals($this->defaultPayloadResponse, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify request reached the handler
        $this->assertNotNull($this->recentlyHandledRequest);
    }
}
