<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @package   Core
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Middleware;

use Horde\Core\Middleware\ConditionalCsrfMiddleware;
use Horde\Http\ResponseFactory;
use Horde\Http\ServerRequest;
use Horde_Exception;
use Horde_Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(ConditionalCsrfMiddleware::class)]
class ConditionalCsrfMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
    }

    /**
     * Build a POST request with optional parsed body and headers.
     *
     * @param array<string, string> $body    POST parameters
     * @param array<string, string> $headers HTTP headers
     *
     * @return ServerRequest
     */
    private function createPostRequest(array $body = [], array $headers = []): ServerRequest
    {
        $request = new ServerRequest(
            'POST',
            'http://localhost/horde/services/ajax.php/horde/ajax/tagger.getTags',
            $headers,
            null,
            '1.1',
            [],
        );
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }

        return $request;
    }

    /**
     * Create a mock handler that returns a 200 response.
     *
     * @return RequestHandlerInterface
     */
    private function createPassthroughHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(
            $this->responseFactory->createResponse(200)
        );

        return $handler;
    }

    /**
     * Create a Horde_Session mock.
     *
     * @param string|null $validToken  Token that checkToken() accepts;
     *                                 null means all tokens are rejected
     *
     * @return Horde_Session
     */
    private function createSessionMock(?string $validToken = null): Horde_Session
    {
        $session = $this->createMock(Horde_Session::class);
        $session->method('checkToken')->willReturnCallback(
            function (string $token) use ($validToken): void {
                if ($validToken === null || $token !== $validToken) {
                    throw new Horde_Exception('Invalid token!');
                }
            }
        );

        return $session;
    }

    // -----------------------------------------------------------------
    // JWT-authenticated requests skip CSRF entirely
    // -----------------------------------------------------------------

    #[Test]
    public function jwtAuthenticatedRequestSkipsCsrfValidation(): void
    {
        $session = $this->createSessionMock();
        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest();
        $request = $request->withAttribute('auth_type', 'jwt');

        $handler = $this->createPassthroughHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function jwtAuthenticatedRequestDoesNotCheckToken(): void
    {
        $session = $this->createMock(Horde_Session::class);
        $session->expects(self::never())->method('checkToken');

        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest();
        $request = $request->withAttribute('auth_type', 'jwt');

        $middleware->process($request, $this->createPassthroughHandler());
    }

    // -----------------------------------------------------------------
    // Session-authenticated requests with valid token
    // -----------------------------------------------------------------

    #[Test]
    public function validTokenInPostBodyPassesThrough(): void
    {
        $token = 'valid-session-token-abc123';
        $session = $this->createSessionMock($token);
        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest(['token' => $token]);

        $handler = $this->createPassthroughHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function validTokenInHeaderPassesThrough(): void
    {
        $token = 'valid-session-token-abc123';
        $session = $this->createSessionMock($token);
        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest([], ['Horde-Session-Token' => $token]);

        $handler = $this->createPassthroughHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postBodyTokenTakesPrecedenceOverHeader(): void
    {
        $correctToken = 'correct-token';
        $wrongToken = 'wrong-token';
        $session = $this->createSessionMock($correctToken);
        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest(
            ['token' => $correctToken],
            ['Horde-Session-Token' => $wrongToken],
        );

        $handler = $this->createPassthroughHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------
    // Session-authenticated requests with invalid token
    // -----------------------------------------------------------------

    #[Test]
    public function invalidTokenReturns403(): void
    {
        $session = $this->createSessionMock('the-real-token');
        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest(['token' => 'wrong-token']);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $handler);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function invalidTokenResponseContainsAjaxTimeoutMessage(): void
    {
        $session = $this->createSessionMock('the-real-token');
        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest(['token' => 'wrong-token']);
        $handler = $this->createPassthroughHandler();

        $response = $middleware->process($request, $handler);
        $body = json_decode((string) $response->getBody(), true);

        self::assertFalse($body['response']);
        self::assertSame('horde.ajaxtimeout', $body['msgs'][0]['type']);
    }

    // -----------------------------------------------------------------
    // Missing token
    // -----------------------------------------------------------------

    #[Test]
    public function missingTokenReturns403(): void
    {
        $session = $this->createSessionMock('any-token');
        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $handler);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function missingTokenResponseContainsAjaxTimeoutMessage(): void
    {
        $session = $this->createSessionMock('any-token');
        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest();
        $handler = $this->createPassthroughHandler();

        $response = $middleware->process($request, $handler);
        $body = json_decode((string) $response->getBody(), true);

        self::assertFalse($body['response']);
        self::assertSame('horde.ajaxtimeout', $body['msgs'][0]['type']);
    }

    // -----------------------------------------------------------------
    // No auth_type attribute (defaults to session behavior)
    // -----------------------------------------------------------------

    #[Test]
    public function noAuthTypeAttributeEnforcesCsrf(): void
    {
        $session = $this->createSessionMock('valid-token');
        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest(['token' => 'valid-token']);

        $handler = $this->createPassthroughHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function noAuthTypeAttributeWithoutTokenRejects(): void
    {
        $session = $this->createSessionMock('valid-token');
        $middleware = new ConditionalCsrfMiddleware($session);

        $request = $this->createPostRequest();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $handler);

        self::assertSame(403, $response->getStatusCode());
    }
}
