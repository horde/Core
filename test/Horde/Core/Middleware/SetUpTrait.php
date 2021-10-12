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
namespace Horde\Core\Middleware;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

use \Horde\Core\Middleware\DemandSessionToken;
use \Horde\Http\RequestFactory;
use \Horde\Http\StreamFactory;
use \Horde\Http\ResponseFactory;
use \Horde\Http\Server\RampageRequestHandler;
use \Horde_Session;
use \Horde_Registry;
use \Horde_Exception;

trait SetUpTrait
{
    protected function setUp(): void
    {
        $this->requestFactory = new RequestFactory();
        $this->streamFactory = new StreamFactory();
        $this->responseFactory = new ResponseFactory();
        $this->session = $this->createMock(Horde_Session::class);
        $this->registry = $this->createMock(Horde_Registry::class);

        $this->defaultPayloadResponse = $this->responseFactory->createResponse(200);
        $this->defaultPayloadHandler = $this->createMock(RequestHandlerInterface::class);
        $this->defaultPayloadHandler->method('handle')->willReturn($this->defaultPayloadResponse);

        $this->handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            [],
            $this->defaultPayloadHandler
        );
    }
}
