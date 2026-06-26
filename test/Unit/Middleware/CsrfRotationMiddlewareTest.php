<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Middleware;

use Horde\Core\Middleware\CsrfRotationMiddleware;
use Horde\Core\Middleware\HordeSessionMiddleware;
use Horde\Core\Session\HordeSession;
use Horde\Http\ResponseFactory;
use Horde\Http\ServerRequest;
use Horde\SessionHandler\SessionId;
use Horde\Token\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(CsrfRotationMiddleware::class)]
final class CsrfRotationMiddlewareTest extends TestCase
{
    private Token $tokenService;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->tokenService = Token::null('test-secret-key');
        $this->responseFactory = new ResponseFactory();
    }

    private function passthroughHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->willReturn($this->responseFactory->createResponse(200));
        return $handler;
    }

    #[Test]
    public function noSessionAttributeLeavesResponseUntouched(): void
    {
        $middleware = new CsrfRotationMiddleware($this->tokenService);
        $request = new ServerRequest('POST', 'http://localhost/api/v1/session/ping');

        $response = $middleware->process($request, $this->passthroughHandler());

        self::assertFalse(
            $response->hasHeader(CsrfRotationMiddleware::RESPONSE_HEADER),
            'X-Csrf-Token must not be set when no session is attached',
        );
    }

    #[Test]
    public function sessionAttributeAddsFreshCsrfHeader(): void
    {
        $middleware = new CsrfRotationMiddleware($this->tokenService);
        $session = new HordeSession(new SessionId('test-sid'));
        $request = (new ServerRequest('POST', 'http://localhost/api/v1/session/ping'))
            ->withAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION, $session);

        $response = $middleware->process($request, $this->passthroughHandler());

        self::assertTrue($response->hasHeader(CsrfRotationMiddleware::RESPONSE_HEADER));
        $token = $response->getHeaderLine(CsrfRotationMiddleware::RESPONSE_HEADER);
        self::assertNotEmpty($token);
        // The token must validate against the same service and seed.
        self::assertTrue(
            $this->tokenService->isValid($token, HordeSession::CSRF_SEED),
            'Emitted token must validate against the CSRF seed',
        );
    }

    #[Test]
    public function twoCallsEmitDifferentTokens(): void
    {
        $middleware = new CsrfRotationMiddleware($this->tokenService);
        $session = new HordeSession(new SessionId('test-sid'));
        $request = (new ServerRequest('POST', 'http://localhost/api/v1/session/ping'))
            ->withAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION, $session);

        $first = $middleware->process($request, $this->passthroughHandler())
            ->getHeaderLine(CsrfRotationMiddleware::RESPONSE_HEADER);
        $second = $middleware->process($request, $this->passthroughHandler())
            ->getHeaderLine(CsrfRotationMiddleware::RESPONSE_HEADER);

        self::assertNotSame($first, $second, 'Each call should mint a fresh token');
        self::assertTrue($this->tokenService->isValid($first, HordeSession::CSRF_SEED));
        self::assertTrue($this->tokenService->isValid($second, HordeSession::CSRF_SEED));
    }
}
