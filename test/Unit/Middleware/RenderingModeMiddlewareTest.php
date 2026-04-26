<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Middleware;

use Horde\Core\Middleware\RenderingModeMiddleware;
use Horde\Core\PageOutput\RenderingMode;
use Horde\Core\PageOutput\RenderingModeResolver;
use Horde\Http\ResponseFactory;
use Horde\Http\ServerRequest;
use Horde\Http\StreamFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RenderingModeMiddleware::class)]
class RenderingModeMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private StreamFactory $streamFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->streamFactory = new StreamFactory();
    }

    private function createRequest(): ServerRequest
    {
        return new ServerRequest(
            'GET',
            'http://localhost/horde/test',
            [],
            null,
            '1.1',
            [],
        );
    }

    #[Test]
    public function middlewareAttachesRenderingModeToRequest(): void
    {
        $resolver = $this->createMock(RenderingModeResolver::class);
        $resolver->method('resolve')->willReturn(RenderingMode::DYNAMIC);

        $middleware = new RenderingModeMiddleware($resolver);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function (ServerRequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return $this->responseFactory->createResponse(200);
        });

        $middleware->process($this->createRequest(), $handler);

        self::assertNotNull($capturedRequest);
        self::assertSame(RenderingMode::DYNAMIC, $capturedRequest->getAttribute('renderingMode'));
    }

    #[Test]
    public function middlewareAttachesResponsiveModeForMobileBrowser(): void
    {
        $resolver = $this->createMock(RenderingModeResolver::class);
        $resolver->method('resolve')->willReturn(RenderingMode::RESPONSIVE);

        $middleware = new RenderingModeMiddleware($resolver);

        $capturedRequest = null;
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function (ServerRequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return $this->responseFactory->createResponse(200);
        });

        $middleware->process($this->createRequest(), $handler);

        self::assertSame(RenderingMode::RESPONSIVE, $capturedRequest->getAttribute('renderingMode'));
    }

    #[Test]
    public function middlewareCallsHandlerWithModifiedRequest(): void
    {
        $resolver = $this->createMock(RenderingModeResolver::class);
        $resolver->method('resolve')->willReturn(RenderingMode::BASIC);

        $middleware = new RenderingModeMiddleware($resolver);

        $response = $this->responseFactory->createResponse(200);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn($response);

        $result = $middleware->process($this->createRequest(), $handler);

        self::assertSame($response, $result);
    }
}
