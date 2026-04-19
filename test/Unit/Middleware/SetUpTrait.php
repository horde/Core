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

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Horde\Core\Middleware\DemandSessionToken;
use Horde\Http\RequestFactory;
use Horde\Http\StreamFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\Server\RampageRequestHandler;
use Horde\Http\ServerRequest;
use Horde_Session;
use Horde_Registry;
use Horde_Exception;
use Horde_Auth_Base;

trait SetUpTrait
{
    protected RequestFactory $requestFactory;
    protected StreamFactory $streamFactory;
    protected ResponseFactory $responseFactory;
    protected Horde_Session $session;
    protected Horde_Registry $registry;
    protected ResponseInterface $defaultPayloadResponse;
    protected RequestHandlerInterface $defaultPayloadHandler;
    protected ?ServerRequest $recentlyHandledRequest;
    protected RampageRequestHandler $handler;
    protected Horde_Auth_Base $authDriver;

    protected function setUp(): void
    {
        $this->requestFactory = new RequestFactory();
        $this->streamFactory = new StreamFactory();
        $this->responseFactory = new ResponseFactory();

        // Use stubs instead of mocks when no expectations are needed
        $this->session = $this->createStub(Horde_Session::class);
        $this->registry = $this->createStub(Horde_Registry::class);

        $this->defaultPayloadResponse = $this->responseFactory->createResponse(200);

        // Use stub for handler, but configure willReturnCallback
        $this->defaultPayloadHandler = $this->createStub(RequestHandlerInterface::class);
        $this->recentlyHandledRequest = null;
        $this->defaultPayloadHandler->method('handle')->willReturnCallback(function ($request) {
            $this->recentlyHandledRequest = $request;
            return $this->defaultPayloadResponse;
        });

        $this->handler = new RampageRequestHandler(
            $this->responseFactory,
            $this->streamFactory,
            [],
            $this->defaultPayloadHandler
        );

        $this->authDriver = $this->createStub(Horde_Auth_Base::class);
    }
}
