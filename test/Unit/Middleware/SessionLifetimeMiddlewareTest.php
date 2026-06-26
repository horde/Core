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

use Horde\Core\Middleware\HordeSessionMiddleware;
use Horde\Core\Middleware\SessionLifetimeMiddleware;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionConfig;
use Horde\Http\ResponseFactory;
use Horde\Http\ServerRequest;
use Horde\SessionHandler\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(SessionLifetimeMiddleware::class)]
final class SessionLifetimeMiddlewareTest extends TestCase
{
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
    }

    private function config(int $lifetime = 3600, int $regenerateInterval = 21600): SessionConfig
    {
        return new SessionConfig(
            cookieName: 'Horde',
            cookieDomain: null,
            cookiePath: '/',
            secure: false,
            lifetime: $lifetime,
            regenerateInterval: $regenerateInterval,
            cacheLimiter: null,
        );
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
    public function noSessionAttributeIsPassThroughWithoutHeaders(): void
    {
        $middleware = new SessionLifetimeMiddleware($this->config());
        $request = new ServerRequest('GET', 'http://localhost/api/v1/something');

        $response = $middleware->process($request, $this->passthroughHandler());

        self::assertFalse($response->hasHeader(SessionLifetimeMiddleware::HEADER_NEXT_PING));
        self::assertFalse($response->hasHeader(SessionLifetimeMiddleware::HEADER_SESSION_TS));
    }

    #[Test]
    public function sessionAttributeWritesLastSeenSlot(): void
    {
        $middleware = new SessionLifetimeMiddleware($this->config());
        $session = new HordeSession(new SessionId('test-sid'));
        $request = (new ServerRequest('POST', 'http://localhost/api/v1/session/ping'))
            ->withAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION, $session);

        $before = time();
        $middleware->process($request, $this->passthroughHandler());
        $after = time();

        $value = $session->getScoped('horde', SessionLifetimeMiddleware::SLOT_LAST_SEEN);
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual($before, $value);
        self::assertLessThanOrEqual($after, $value);
    }

    #[Test]
    public function deadlineNotPastDoesNotScheduleRotation(): void
    {
        $middleware = new SessionLifetimeMiddleware($this->config());
        $session = new HordeSession(new SessionId('test-sid'));
        $session->setRegenerationDeadline(time() + 3600); // 1h in the future
        $request = (new ServerRequest('POST', 'http://localhost/api/v1/session/ping'))
            ->withAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION, $session);

        $middleware->process($request, $this->passthroughHandler());

        self::assertFalse($session->shouldRegenerate());
    }

    #[Test]
    public function deadlinePastSchedulesRotation(): void
    {
        $middleware = new SessionLifetimeMiddleware($this->config());
        $session = new HordeSession(new SessionId('test-sid'));
        $session->setRegenerationDeadline(time() - 60); // 1m in the past
        $request = (new ServerRequest('POST', 'http://localhost/api/v1/session/ping'))
            ->withAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION, $session);

        $middleware->process($request, $this->passthroughHandler());

        self::assertTrue($session->shouldRegenerate());
    }

    #[Test]
    public function emitsNextPingHeaderAsLifetimeOverThree(): void
    {
        // lifetime 3600 → next ping 1200
        $middleware = new SessionLifetimeMiddleware($this->config(lifetime: 3600));
        $session = new HordeSession(new SessionId('test-sid'));
        $request = (new ServerRequest('POST', 'http://localhost/api/v1/session/ping'))
            ->withAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION, $session);

        $response = $middleware->process($request, $this->passthroughHandler());

        self::assertSame(
            '1200',
            $response->getHeaderLine(SessionLifetimeMiddleware::HEADER_NEXT_PING),
        );
    }

    #[Test]
    public function nextPingFlooredAt60Seconds(): void
    {
        // lifetime 60 → /3 = 20, but the floor is 60
        $middleware = new SessionLifetimeMiddleware($this->config(lifetime: 60));
        $session = new HordeSession(new SessionId('test-sid'));
        $request = (new ServerRequest('POST', 'http://localhost/api/v1/session/ping'))
            ->withAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION, $session);

        $response = $middleware->process($request, $this->passthroughHandler());

        self::assertSame(
            (string) SessionLifetimeMiddleware::MIN_PING_INTERVAL,
            $response->getHeaderLine(SessionLifetimeMiddleware::HEADER_NEXT_PING),
        );
    }

    #[Test]
    public function nextPingFallbackWhenLifetimeIsZero(): void
    {
        // lifetime 0 → constructor default
        $middleware = new SessionLifetimeMiddleware($this->config(lifetime: 0), defaultNextPingSeconds: 900);
        $session = new HordeSession(new SessionId('test-sid'));
        $request = (new ServerRequest('POST', 'http://localhost/api/v1/session/ping'))
            ->withAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION, $session);

        $response = $middleware->process($request, $this->passthroughHandler());

        self::assertSame(
            '900',
            $response->getHeaderLine(SessionLifetimeMiddleware::HEADER_NEXT_PING),
        );
    }

    #[Test]
    public function emitsSessionTsHeaderAsServerTime(): void
    {
        $middleware = new SessionLifetimeMiddleware($this->config());
        $session = new HordeSession(new SessionId('test-sid'));
        $request = (new ServerRequest('POST', 'http://localhost/api/v1/session/ping'))
            ->withAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION, $session);

        $before = time();
        $response = $middleware->process($request, $this->passthroughHandler());
        $after = time();

        $ts = (int) $response->getHeaderLine(SessionLifetimeMiddleware::HEADER_SESSION_TS);
        self::assertGreaterThanOrEqual($before, $ts);
        self::assertLessThanOrEqual($after, $ts);
    }
}
