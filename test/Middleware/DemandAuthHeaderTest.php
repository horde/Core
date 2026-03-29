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

use Horde_Test_Case as HordeTestCase;
use Horde\Core\Middleware\DemandAuthHeader;

class DemandAuthHeaderTest extends HordeTestCase
{
    use SetUpTrait;

    protected function getMiddleware(string $type = 'BASIC', string $realm = '', string $charset = 'UTF-8')
    {
        return new DemandAuthHeader(
            $this->responseFactory,
            $type,
            $realm,
            $charset
        );
    }

    public function testHeaderMissingReturns401()
    {
        $middleware = $this->getMiddleware('BASIC', 'TestRealm');
        $request = $this->requestFactory->createServerRequest('GET', '/test');

        $response = $middleware->process($request, $this->handler);

        // Should return 401 Unauthorized
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Unauthorized', $response->getReasonPhrase());

        // Should include WWW-Authenticate header
        $this->assertTrue($response->hasHeader('WWW-Authenticate'));
        $authHeader = $response->getHeaderLine('WWW-Authenticate');
        $this->assertStringContainsString('BASIC', $authHeader);
        $this->assertStringContainsString('realm="TestRealm"', $authHeader);
        $this->assertStringContainsString('charset="UTF-8"', $authHeader);
    }

    public function testHeaderPresentCallsHandler()
    {
        $middleware = $this->getMiddleware();
        $request = $this->requestFactory->createServerRequest('GET', '/test')
            ->withHeader('Authorization', 'Basic dGVzdDp0ZXN0');

        $response = $middleware->process($request, $this->handler);

        // Should pass through to handler and return its response
        $this->assertEquals($this->defaultPayloadResponse, $response);
        $this->assertEquals(200, $response->getStatusCode());

        // Verify request was actually passed to handler
        $this->assertNotNull($this->recentlyHandledRequest);
        $this->assertTrue($this->recentlyHandledRequest->hasHeader('Authorization'));
    }

    public function testCustomChallengeWithoutCharset()
    {
        $middleware = $this->getMiddleware('Bearer', 'API', '');
        $request = $this->requestFactory->createServerRequest('GET', '/api/test');

        $response = $middleware->process($request, $this->handler);

        $this->assertEquals(401, $response->getStatusCode());
        $authHeader = $response->getHeaderLine('WWW-Authenticate');
        $this->assertStringContainsString('Bearer', $authHeader);
        $this->assertStringContainsString('realm="API"', $authHeader);
        $this->assertStringNotContainsString('charset', $authHeader);
    }
}
