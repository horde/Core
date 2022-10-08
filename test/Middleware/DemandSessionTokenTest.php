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
    use SetUpTrait;

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

        $this->session->method('checkToken')->willThrowException(new Horde_Exception('test'));
        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSessionTokenCorrect()
    {
        $middleware = $this->getMiddleware();

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        $response = $middleware->process($request, $this->handler);

        $this->assertEquals($this->defaultPayloadResponse, $response);
    }
}
