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

use Horde\Core\Middleware\DemandSessionToken;
use Horde\Core\Session\HordeSession;
use Horde\Token\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DemandSessionToken::class)]
class DemandSessionTokenTest extends TestCase
{
    use SetUpTrait {
        setUp as protected traitSetUp;
    }

    protected Token $tokenService;

    protected function setUp(): void
    {
        $this->traitSetUp();
        // Token is a final class — use a real instance with NullStorage so the
        // test exercises the actual generate/isValid round-trip without
        // requiring a database or filesystem backend.
        $this->tokenService = Token::null('test-secret-key');
    }

    protected function getMiddleware(): DemandSessionToken
    {
        return new DemandSessionToken(
            $this->responseFactory,
            $this->streamFactory,
            $this->tokenService
        );
    }

    public function testSessionTokenMissing(): void
    {
        $middleware = $this->getMiddleware();

        $request = $this->requestFactory->createServerRequest('GET', '/test');
        // No Horde-Session-Token header set — must be rejected.
        $response = $middleware->process($request, $this->handler);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Horde-Session-Token', $response->getReasonPhrase());
    }

    public function testSessionTokenIncorrect(): void
    {
        $middleware = $this->getMiddleware();

        $request = $this->requestFactory
            ->createServerRequest('GET', '/test')
            ->withHeader('Horde-Session-Token', 'not-a-valid-token');
        $response = $middleware->process($request, $this->handler);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSessionTokenCorrect(): void
    {
        $middleware = $this->getMiddleware();

        $generated = $this->tokenService->generate(HordeSession::CSRF_SEED);

        $request = $this->requestFactory
            ->createServerRequest('GET', '/test')
            ->withHeader('Horde-Session-Token', $generated->token);
        $response = $middleware->process($request, $this->handler);

        // Should pass through to handler.
        $this->assertEquals($this->defaultPayloadResponse, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($this->recentlyHandledRequest);
    }
}


