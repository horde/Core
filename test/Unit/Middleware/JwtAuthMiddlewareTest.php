<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Core\Auth\Jwt\JwtService;
use Horde\Core\Auth\Jwt\VerifiedJwt;
use Horde\Core\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use InvalidArgumentException;

/**
 * Unit Test: JwtAuthMiddleware
 *
 * Each test builds its own mocks and asserts concrete expects() counts
 * per the testing convention. The shared setUp() pattern was retired
 * because PHPUnit 13 raises notices on mocks with no expectations
 * configured, and the test contract is clearer when each test owns
 * its collaborators outright.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(JwtAuthMiddleware::class)]
class JwtAuthMiddlewareTest extends TestCase
{
    public function testProcessWithNoAuthHeaderAndNotRequired(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('');

        // Required: false. Empty header. Middleware must NOT consult
        // jwtService and must hand the request to the inner handler.
        $jwtService->expects($this->never())->method('extractTokenFromHeader');
        $jwtService->expects($this->never())->method('verifyAccessToken');

        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $middleware = new JwtAuthMiddleware($jwtService, required: false);
        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testProcessWithNoAuthHeaderAndRequired(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('');

        // Required: true. Empty header. Middleware must short-circuit
        // before touching jwtService and must NOT call the inner handler.
        $jwtService->expects($this->never())->method('extractTokenFromHeader');
        $jwtService->expects($this->never())->method('verifyAccessToken');
        $handler->expects($this->never())->method('handle');

        $middleware = new JwtAuthMiddleware($jwtService, required: true);
        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals(401, $result->getStatusCode());
    }

    public function testProcessWithValidJwtToken(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer valid-jwt-token');

        $jwtService->expects($this->once())
            ->method('extractTokenFromHeader')
            ->with('Bearer valid-jwt-token')
            ->willReturn('valid-jwt-token');

        $verifiedJwt = new VerifiedJwt('valid-jwt-token', [
            'sub' => 'user123',
            'jti' => 'token-id',
            'iss' => 'horde.example.com',
        ]);

        $jwtService->expects($this->once())
            ->method('verifyAccessToken')
            ->with('valid-jwt-token')
            ->willReturn($verifiedJwt);

        // Middleware threads four request attributes through chained
        // withAttribute calls (jwt, jwt_user_id, jwt_claims, auth_type).
        // We don't care about the exact intermediate request objects,
        // only that the chain reaches the handler. Returning $request
        // from each withAttribute() lets the chain collapse onto itself.
        $request->expects($this->exactly(4))
            ->method('withAttribute')
            ->willReturn($request);

        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $middleware = new JwtAuthMiddleware($jwtService);
        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testProcessWithInvalidJwtTokenAndNotRequired(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer invalid-token');

        $jwtService->expects($this->once())
            ->method('extractTokenFromHeader')
            ->with('Bearer invalid-token')
            ->willReturn('invalid-token');

        $jwtService->expects($this->once())
            ->method('verifyAccessToken')
            ->with('invalid-token')
            ->willThrowException(new InvalidArgumentException('Token expired'));

        // Required: false. Verification failure falls back to session auth.
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $middleware = new JwtAuthMiddleware($jwtService, required: false);
        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testProcessWithInvalidJwtTokenAndRequired(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer invalid-token');

        $jwtService->expects($this->once())
            ->method('extractTokenFromHeader')
            ->with('Bearer invalid-token')
            ->willReturn('invalid-token');

        $jwtService->expects($this->once())
            ->method('verifyAccessToken')
            ->with('invalid-token')
            ->willThrowException(new InvalidArgumentException('Token expired'));

        // Required: true. Verification failure short-circuits with 401.
        $handler->expects($this->never())->method('handle');

        $middleware = new JwtAuthMiddleware($jwtService, required: true);
        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals(401, $result->getStatusCode());
    }

    public function testProcessWithHordeClaims(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer token-with-horde-claims');

        $jwtService->expects($this->once())
            ->method('extractTokenFromHeader')
            ->willReturn('token-with-horde-claims');

        $verifiedJwt = new VerifiedJwt('token-with-horde-claims', [
            'sub' => 'user123',
            'horde' => [
                'apps' => ['imp', 'ingo'],
                'permissions' => ['admin'],
            ],
        ]);

        $jwtService->expects($this->once())
            ->method('verifyAccessToken')
            ->willReturn($verifiedJwt);

        // Five withAttribute calls when horde claims are present:
        // jwt, jwt_user_id, jwt_claims, auth_type, plus
        // jwt_horde_claims for the embedded horde namespace.
        $request->expects($this->exactly(5))
            ->method('withAttribute')
            ->willReturn($request);

        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $middleware = new JwtAuthMiddleware($jwtService);
        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testProcessWithNullTokenFromHeader(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('InvalidHeader');

        $jwtService->expects($this->once())
            ->method('extractTokenFromHeader')
            ->with('InvalidHeader')
            ->willReturn(null);

        // Header was non-empty so extractTokenFromHeader was consulted,
        // but it returned null. With required:false the middleware
        // forwards to session auth.
        $jwtService->expects($this->never())->method('verifyAccessToken');

        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $middleware = new JwtAuthMiddleware($jwtService, required: false);
        $result = $middleware->process($request, $handler);

        $this->assertSame($response, $result);
    }

    public function testProcessRequiredModePreventsSessionFallback(): void
    {
        $jwtService = $this->createMock(JwtService::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('InvalidHeader');

        $jwtService->expects($this->once())
            ->method('extractTokenFromHeader')
            ->willReturn(null);

        // Required: true. Null token short-circuits with 401; the inner
        // handler must not be called.
        $jwtService->expects($this->never())->method('verifyAccessToken');
        $handler->expects($this->never())->method('handle');

        $middleware = new JwtAuthMiddleware($jwtService, required: true);
        $result = $middleware->process($request, $handler);

        $this->assertEquals(401, $result->getStatusCode());
    }
}
