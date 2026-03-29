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

use Horde\Test\TestCase;
use Horde\Core\Middleware\DemandAuthenticatedUser;

class DemandAuthenticatedUserTest extends TestCase
{
    use SetUpTrait;

    protected function getMiddleware()
    {
        return new DemandAuthenticatedUser(
            $this->responseFactory
        );
    }

    public function testAttributeMissing()
    {
        $middleware = $this->getMiddleware();

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        // Should return 401 Unauthorized
        $this->assertEquals(401, $response->getStatusCode());

        // Handler should NOT be called
        $this->assertNull($this->recentlyHandledRequest);
    }

    public function testAttributeExistsButEmpty()
    {
        $middleware = $this->getMiddleware();

        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withAttribute('HORDE_AUTHENTICATED_USER', '');
        $response = $middleware->process($request, $this->handler);

        // Should return 401 Unauthorized (empty string = not authenticated)
        $this->assertEquals(401, $response->getStatusCode());

        // Handler should NOT be called
        $this->assertNull($this->recentlyHandledRequest);
    }

    public function testAttributeExistsAndNotEmpty()
    {
        $middleware = $this->getMiddleware();

        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withAttribute('HORDE_AUTHENTICATED_USER', 'testUser');
        $response = $middleware->process($request, $this->handler);

        // Should pass through to handler
        $this->assertEquals($this->defaultPayloadResponse, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify request reached handler with correct attribute
        $this->assertNotNull($this->recentlyHandledRequest);
        $this->assertEquals('testUser', $this->recentlyHandledRequest->getAttribute('HORDE_AUTHENTICATED_USER'));
    }
}
