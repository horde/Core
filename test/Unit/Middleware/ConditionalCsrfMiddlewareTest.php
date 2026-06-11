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
use Horde\Core\Session\HordeSession;
use Horde\Http\ResponseFactory;
use Horde\Http\ServerRequest;
use Horde\Token\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(ConditionalCsrfMiddleware::class)]
class ConditionalCsrfMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;
    private Token $tokenService;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        // Token is final; use a real instance with NullStorage so the test
        // exercises the actual generate/isValid round-trip.
        $this->tokenService = Token::null('test-secret-key');
    }

    /**
     * Build a POST request with optional parsed body and headers.
     *
     * @param array<string, string> $body    POST parameters
     * @param array<string, string> $headers HTTP headers
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
     * Generate a fresh CSRF token bound to the session seed.
     */
    private function freshToken(): string
    {
        return $this->tokenService->generate(HordeSession::CSRF_SEED)->token;
    }

    // -----------------------------------------------------------------
    // JWT-authenticated requests skip CSRF entirely
    // -----------------------------------------------------------------

    #[Test]
    public function jwtAuthenticatedRequestSkipsCsrfValidation(): void
    {
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

        $request = $this->createPostRequest();
        $request = $request->withAttribute('auth_type', 'jwt');

        $handler = $this->createPassthroughHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function jwtAuthenticatedRequestEvenWithoutTokenIsAccepted(): void
    {
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

        $request = $this->createPostRequest();
        $request = $request->withAttribute('auth_type', 'jwt');

        $handler = $this->createPassthroughHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    // -----------------------------------------------------------------
    // Session-authenticated requests with valid token
    // -----------------------------------------------------------------

    #[Test]
    public function validTokenInPostBodyPassesThrough(): void
    {
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

        $request = $this->createPostRequest(['token' => $this->freshToken()]);

        $handler = $this->createPassthroughHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function validTokenInHeaderPassesThrough(): void
    {
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

        $request = $this->createPostRequest(
            [],
            ['Horde-Session-Token' => $this->freshToken()],
        );

        $handler = $this->createPassthroughHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function postBodyTokenTakesPrecedenceOverHeader(): void
    {
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

        // Body holds a valid token, header holds garbage. The middleware
        // should validate only the body's token and pass through.
        $request = $this->createPostRequest(
            ['token' => $this->freshToken()],
            ['Horde-Session-Token' => 'wrong-token'],
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
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

        $request = $this->createPostRequest(['token' => 'wrong-token']);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $handler);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function invalidTokenResponseContainsAjaxTimeoutMessage(): void
    {
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

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
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

        $request = $this->createPostRequest();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $handler);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function missingTokenResponseContainsAjaxTimeoutMessage(): void
    {
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

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
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

        $request = $this->createPostRequest(['token' => $this->freshToken()]);

        $handler = $this->createPassthroughHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function noAuthTypeAttributeWithoutTokenRejects(): void
    {
        $middleware = new ConditionalCsrfMiddleware($this->tokenService);

        $request = $this->createPostRequest();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $middleware->process($request, $handler);

        self::assertSame(403, $response->getStatusCode());
    }
}
