<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Test\Unit\Middleware;

use Horde\Core\Middleware\HordeSessionMiddleware;
use Horde\Core\Middleware\JwtSessionLoader;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\HordeSessionFactory;
use Horde\Core\Session\SessionConfig;
use Horde\SessionHandler\Exception\SessionException;
use Horde\SessionHandler\SessionHandler;
use Horde\SessionHandler\SessionStorageBackend;
use Horde\SessionHandler\Storage\BuiltinBackend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(HordeSessionMiddleware::class)]
class HordeSessionMiddlewareTest extends TestCase
{
    use SetUpTrait {
        setUp as protected traitSetUp;
    }

    protected function setUp(): void
    {
        $this->traitSetUp();
    }

    private function config(string $cookieName = 'Horde'): SessionConfig
    {
        return new SessionConfig(
            cookieName: $cookieName,
            cookieDomain: null,
            cookiePath: '/',
            secure: false,
            lifetime: 0,
            regenerateInterval: SessionConfig::DEFAULT_REGENERATE_INTERVAL,
            cacheLimiter: null,
        );
    }

    private function configCookieDisabled(string $cookieName = 'Horde'): SessionConfig
    {
        return new SessionConfig(
            cookieName: $cookieName,
            cookieDomain: null,
            cookiePath: '/',
            secure: false,
            lifetime: 0,
            regenerateInterval: SessionConfig::DEFAULT_REGENERATE_INTERVAL,
            cacheLimiter: null,
            serverName: '',
            cookieDisabled: true,
        );
    }

    private function realHandler(): SessionHandler
    {
        return new SessionHandler(
            new BuiltinBackend(),
            sessionFactory: new HordeSessionFactory(),
        );
    }

    private function middleware(SessionHandler $handler, ?SessionConfig $config = null): HordeSessionMiddleware
    {
        return new HordeSessionMiddleware(
            $handler,
            $config ?? $this->config(),
            new NullLogger(),
        );
    }

    private function requestWithCookies(array $cookies)
    {
        $request = $this->requestFactory->createServerRequest('GET', '/test');
        return $request->withCookieParams($cookies);
    }

    /** Pull a Set-Cookie header value matching the cookie name. */
    private function setCookieFor(string $name, $response): ?string
    {
        foreach ($response->getHeader('Set-Cookie') as $line) {
            if (str_starts_with($line, $name . '=')) {
                return $line;
            }
        }
        return null;
    }

    // ---------------------------------------------------------------
    // No cookie -> mint, set attribute, emit Set-Cookie
    // ---------------------------------------------------------------

    #[Test]
    public function testNoCookieMintsFreshSession(): void
    {
        $handler = $this->realHandler();
        $response = $this->middleware($handler)->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );

        $session = $this->recentlyHandledRequest
            ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);

        self::assertInstanceOf(HordeSession::class, $session);
        $setCookie = $this->setCookieFor('Horde', $response);
        self::assertNotNull($setCookie, 'fresh session must emit Set-Cookie');
        self::assertStringContainsString((string) $session->getId(), $setCookie);
    }

    // ---------------------------------------------------------------
    // Valid cookie + valid row -> load, attribute, no Set-Cookie when steady
    // ---------------------------------------------------------------

    #[Test]
    public function testValidCookieLoadsExistingSession(): void
    {
        $handler = $this->realHandler();
        // Pre-populate a session.
        $existing = $handler->create();
        $existing->setScoped('horde', 'auth/userId', 'alice');
        $handler->save($existing);
        $sid = (string) $existing->getId();

        $response = $this->middleware($handler)->process(
            $this->requestWithCookies(['Horde' => $sid]),
            $this->defaultPayloadHandler,
        );

        $session = $this->recentlyHandledRequest
            ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);

        self::assertInstanceOf(HordeSession::class, $session);
        self::assertSame('alice', $session->getScoped('horde', 'auth/userId'));
        self::assertNull(
            $this->setCookieFor('Horde', $response),
            'steady-state load must not re-emit Set-Cookie',
        );
    }

    // ---------------------------------------------------------------
    // Valid cookie but no session row -> mint fresh, emit Set-Cookie
    // ---------------------------------------------------------------

    #[Test]
    public function testInvalidCookieMintsFreshSession(): void
    {
        $handler = $this->realHandler();
        $response = $this->middleware($handler)->process(
            // session id format is permissive (a-zA-Z0-9,-) but the row
            // doesn't exist.
            $this->requestWithCookies(['Horde' => 'nonexistent-id-1234']),
            $this->defaultPayloadHandler,
        );

        $session = $this->recentlyHandledRequest
            ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
        self::assertInstanceOf(HordeSession::class, $session);
        self::assertNotSame('nonexistent-id-1234', (string) $session->getId());

        $setCookie = $this->setCookieFor('Horde', $response);
        self::assertNotNull($setCookie, 'cookie pointing at missing row must trigger fresh Set-Cookie');
        self::assertStringContainsString((string) $session->getId(), $setCookie);
    }

    // ---------------------------------------------------------------
    // Malformed cookie value -> mint fresh
    // ---------------------------------------------------------------

    #[Test]
    public function testMalformedCookieValueMintsFreshSession(): void
    {
        $handler = $this->realHandler();
        // SessionId pattern rejects values with whitespace, etc.
        $response = $this->middleware($handler)->process(
            $this->requestWithCookies(['Horde' => 'has space']),
            $this->defaultPayloadHandler,
        );

        $session = $this->recentlyHandledRequest
            ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
        self::assertInstanceOf(HordeSession::class, $session);
        self::assertNotNull($this->setCookieFor('Horde', $response));
    }

    // ---------------------------------------------------------------
    // Dirty session -> persisted
    // ---------------------------------------------------------------

    #[Test]
    public function testDirtySessionIsPersistedOnTheWayOut(): void
    {
        $handler = $this->realHandler();
        // Wire the inner handler to mutate the session.
        $this->defaultPayloadHandler = $this->createStub(\Psr\Http\Server\RequestHandlerInterface::class);
        $this->defaultPayloadHandler->method('handle')->willReturnCallback(function ($request) {
            $this->recentlyHandledRequest = $request;
            $session = $request->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
            self::assertInstanceOf(HordeSession::class, $session);
            $session->setScoped('horde', 'auth/userId', 'alice');
            return $this->defaultPayloadResponse;
        });

        $this->middleware($handler)->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );

        // Reload via a fresh handler to confirm persistence took.
        $reloaded = $handler->load(
            $this->recentlyHandledRequest
                ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION)
                ->getId(),
        );
        self::assertInstanceOf(HordeSession::class, $reloaded);
        self::assertSame('alice', $reloaded->getScoped('horde', 'auth/userId'));
    }

    // ---------------------------------------------------------------
    // markDestroyed -> destroy row, clearing Set-Cookie
    // ---------------------------------------------------------------

    #[Test]
    public function testMarkDestroyedDestroysRowAndClearsCookie(): void
    {
        $handler = $this->realHandler();
        $existing = $handler->create();
        $handler->save($existing);
        $sid = (string) $existing->getId();

        $this->defaultPayloadHandler = $this->createStub(\Psr\Http\Server\RequestHandlerInterface::class);
        $this->defaultPayloadHandler->method('handle')->willReturnCallback(function ($request) {
            $this->recentlyHandledRequest = $request;
            $session = $request->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
            $session->markDestroyed();
            return $this->defaultPayloadResponse;
        });

        $response = $this->middleware($handler)->process(
            $this->requestWithCookies(['Horde' => $sid]),
            $this->defaultPayloadHandler,
        );

        // The row is gone.
        self::assertNull($handler->load($existing->getId()));

        // The Set-Cookie clears the cookie.
        $setCookie = $this->setCookieFor('Horde', $response);
        self::assertNotNull($setCookie);
        self::assertStringContainsString('Max-Age=0', $setCookie);
    }

    // ---------------------------------------------------------------
    // scheduleRegeneration -> rotate, new id in Set-Cookie, payload preserved
    // ---------------------------------------------------------------

    #[Test]
    public function testScheduleRegenerationRotatesIdAndPersists(): void
    {
        $handler = $this->realHandler();
        $existing = $handler->create();
        $existing->setScoped('horde', 'auth/userId', 'alice');
        $handler->save($existing);
        $oldSid = (string) $existing->getId();

        $this->defaultPayloadHandler = $this->createStub(\Psr\Http\Server\RequestHandlerInterface::class);
        $this->defaultPayloadHandler->method('handle')->willReturnCallback(function ($request) {
            $this->recentlyHandledRequest = $request;
            $session = $request->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
            $session->scheduleRegeneration();
            return $this->defaultPayloadResponse;
        });

        $response = $this->middleware($handler)->process(
            $this->requestWithCookies(['Horde' => $oldSid]),
            $this->defaultPayloadHandler,
        );

        // Old row is gone.
        self::assertNull($handler->load($existing->getId()));

        // Set-Cookie carries a different id than the old one.
        $setCookie = $this->setCookieFor('Horde', $response);
        self::assertNotNull($setCookie);
        self::assertStringNotContainsString($oldSid, $setCookie);

        // Payload preserved under whatever new id was minted: extract
        // the new id from the Set-Cookie line and reload.
        preg_match('/^Horde=([^;]+);/', $setCookie, $m);
        self::assertNotEmpty($m[1] ?? '', 'Set-Cookie must contain the new id');
        $newSid = $m[1];
        $reloaded = $handler->load(new \Horde\SessionHandler\SessionId($newSid));
        self::assertInstanceOf(HordeSession::class, $reloaded);
        self::assertSame('alice', $reloaded->getScoped('horde', 'auth/userId'));
    }

    // ---------------------------------------------------------------
    // Deadline-elapsed rotation: a controller that consults the
    // regeneration deadline and schedules a rotation when it has
    // passed. End-to-end against a real BuiltinBackend.
    // ---------------------------------------------------------------

    #[Test]
    public function testDeadlineElapsedTriggersRotationAndRefreshesDeadline(): void
    {
        $handler = $this->realHandler();
        $existing = $handler->create();
        $existing->setScoped('horde', 'auth/userId', 'alice');
        // Drive _r into the past so the deadline check fires.
        $existing->setRegenerationDeadline(time() - 60);
        $handler->save($existing);
        $oldSid = (string) $existing->getId();

        // Controller pattern: read the deadline marker, schedule a
        // rotation if it has elapsed. The middleware acts on the
        // marker on response emit.
        $this->defaultPayloadHandler = $this->createStub(\Psr\Http\Server\RequestHandlerInterface::class);
        $this->defaultPayloadHandler->method('handle')->willReturnCallback(function ($request) {
            $this->recentlyHandledRequest = $request;
            $session = $request->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
            $deadline = $session->getRegenerationDeadline();
            if ($deadline !== null && time() >= $deadline) {
                $session->scheduleRegeneration();
            }
            return $this->defaultPayloadResponse;
        });

        $response = $this->middleware($handler)->process(
            $this->requestWithCookies(['Horde' => $oldSid]),
            $this->defaultPayloadHandler,
        );

        // Old row is gone.
        self::assertNull($handler->load($existing->getId()));

        // Set-Cookie carries a different id.
        $setCookie = $this->setCookieFor('Horde', $response);
        self::assertNotNull($setCookie);
        self::assertStringNotContainsString($oldSid, $setCookie);

        preg_match('/^Horde=([^;]+);/', $setCookie, $m);
        $newSid = $m[1];
        $reloaded = $handler->load(new \Horde\SessionHandler\SessionId($newSid));
        self::assertInstanceOf(HordeSession::class, $reloaded);

        // Payload preserved.
        self::assertSame('alice', $reloaded->getScoped('horde', 'auth/userId'));

        // Deadline refreshed: new row's _r is in the future, anchored
        // at finaliseRegenerated's nextRegenerationDeadline call.
        $newDeadline = $reloaded->getRegenerationDeadline();
        self::assertNotNull($newDeadline);
        self::assertGreaterThan(time(), $newDeadline);
    }

    // ---------------------------------------------------------------
    // JwtSessionLoader already populated the attribute
    // ---------------------------------------------------------------

    #[Test]
    public function testHonoursPreExistingSessionAttribute(): void
    {
        $handler = $this->realHandler();
        $preLoaded = $handler->create();
        $preLoaded->setScoped('horde', 'auth/userId', 'jwtuser');

        // Simulate JwtSessionLoader having populated the attribute.
        $request = $this->requestWithCookies([])
            ->withAttribute(JwtSessionLoader::ATTRIBUTE_SESSION, $preLoaded);

        $this->middleware($handler)->process($request, $this->defaultPayloadHandler);

        $observed = $this->recentlyHandledRequest
            ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);

        self::assertSame($preLoaded, $observed);
    }

    // ---------------------------------------------------------------
    // Backend exception on load -> mint fresh, no exception escapes
    // ---------------------------------------------------------------

    #[Test]
    public function testLoadExceptionFallsBackToFreshSession(): void
    {
        // Stub backend that throws on load() but tolerates create()/save().
        $backend = $this->createMock(SessionStorageBackend::class);
        $backend->expects(self::once())
            ->method('load')
            ->willThrowException(new SessionException('backend down'));
        // create() doesn't go through the backend; save() does.
        // We don't constrain save here because the fresh session has no
        // dirty data and isDirty() will be false.

        $handler = new SessionHandler(
            $backend,
            sessionFactory: new HordeSessionFactory(),
        );

        $this->middleware($handler)->process(
            $this->requestWithCookies(['Horde' => 'will-fail']),
            $this->defaultPayloadHandler,
        );

        $session = $this->recentlyHandledRequest
            ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
        self::assertInstanceOf(HordeSession::class, $session);
    }

    // ---------------------------------------------------------------
    // Returned response is the inner handler's response
    // ---------------------------------------------------------------

    #[Test]
    public function testReturnsInnerHandlerResponse(): void
    {
        $handler = $this->realHandler();
        $response = $this->middleware($handler)->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );

        // The inner handler returned $this->defaultPayloadResponse with
        // status 200. The middleware may have added Set-Cookie headers
        // but the body/status is preserved.
        self::assertSame(200, $response->getStatusCode());
    }

    // ---------------------------------------------------------------
    // cookieDisabled mode: no Set-Cookie on any path
    // ---------------------------------------------------------------

    #[Test]
    public function testCookieDisabledOmitsSetCookieOnFreshSession(): void
    {
        $handler = $this->realHandler();
        $response = $this->middleware($handler, $this->configCookieDisabled())->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );

        $session = $this->recentlyHandledRequest
            ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);

        self::assertInstanceOf(HordeSession::class, $session);
        self::assertNull(
            $this->setCookieFor('Horde', $response),
            'cookieDisabled must suppress Set-Cookie when minting a fresh session',
        );
    }

    #[Test]
    public function testCookieDisabledOmitsSetCookieOnDestroy(): void
    {
        $handler = $this->realHandler();
        $cfg = $this->configCookieDisabled();

        // First request mints a session.
        $first = $this->middleware($handler, $cfg)->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );
        $session = $this->recentlyHandledRequest
            ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
        self::assertInstanceOf(HordeSession::class, $session);
        $sid = (string) $session->getId();

        // Mark the session destroyed and re-run middleware to exercise
        // the destroy path. A named test handler that calls
        // markDestroyed on the session attribute satisfies the no-
        // anonymous-classes-in-DI guidance and also keeps the test
        // body readable.
        $destroyHandler = new MarkDestroyedHandler($this->defaultPayloadResponse);

        $secondRequest = $this->requestFactory
            ->createServerRequest('GET', '/test')
            ->withCookieParams(['Horde' => $sid]);
        $second = $this->middleware($handler, $cfg)->process($secondRequest, $destroyHandler);

        self::assertNull(
            $this->setCookieFor('Horde', $second),
            'cookieDisabled must suppress clearing-cookie emission on destroy',
        );
    }

    #[Test]
    public function testCookieDisabledOmitsSetCookieOnRegenerate(): void
    {
        $handler = $this->realHandler();
        $cfg = $this->configCookieDisabled();

        $first = $this->middleware($handler, $cfg)->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );
        $session = $this->recentlyHandledRequest
            ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
        $sid = (string) $session->getId();

        $regenHandler = new ScheduleRegenerationHandler($this->defaultPayloadResponse);

        $secondRequest = $this->requestFactory
            ->createServerRequest('GET', '/test')
            ->withCookieParams(['Horde' => $sid]);
        $second = $this->middleware($handler, $cfg)->process($secondRequest, $regenHandler);

        self::assertNull(
            $this->setCookieFor('Horde', $second),
            'cookieDisabled must suppress Set-Cookie emission on rotation',
        );
    }

    // ---------------------------------------------------------------
    // Cookie attribute wire-format coverage
    // ---------------------------------------------------------------

    #[Test]
    public function testSetCookieReflectsSessionConfigAttributes(): void
    {
        // Construct a SessionConfig with non-default values for every
        // attribute the middleware emits. Run the mint path. Assert the
        // resulting Set-Cookie carries each attribute exactly. Locks
        // the wire-format contract documented in
        // doc/SESSION_HANDLING.md.
        $config = new SessionConfig(
            cookieName: 'CustomSid',
            cookieDomain: '.example.com',
            cookiePath: '/horde',
            secure: true,
            lifetime: 7200,
            regenerateInterval: SessionConfig::DEFAULT_REGENERATE_INTERVAL,
            cacheLimiter: null,
            serverName: 'horde.example.com',
        );

        $handler = $this->realHandler();
        $response = $this->middleware($handler, $config)->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );

        $setCookie = $this->setCookieFor('CustomSid', $response);
        self::assertNotNull($setCookie, 'mint must emit Set-Cookie');

        // Cookie value: the session id mint produced. Verify shape
        // through the request attribute.
        $session = $this->recentlyHandledRequest
            ->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
        self::assertInstanceOf(HordeSession::class, $session);
        $sid = (string) $session->getId();

        self::assertStringContainsString("CustomSid={$sid}", $setCookie);
        self::assertStringContainsString('Max-Age=7200', $setCookie);
        self::assertStringContainsString('Path=/horde', $setCookie);
        self::assertStringContainsString('Domain=.example.com', $setCookie);
        self::assertStringContainsString('Secure', $setCookie);
        self::assertStringContainsString('HttpOnly', $setCookie);
        self::assertStringContainsString('SameSite=Lax', $setCookie);
    }

    #[Test]
    public function testSetCookieOmitsMaxAgeWhenLifetimeIsZero(): void
    {
        // lifetime=0 means "browser session cookie": no Max-Age, no
        // Expires. The cookie disappears when the browser closes.
        $config = new SessionConfig(
            cookieName: 'Horde',
            cookieDomain: null,
            cookiePath: '/',
            secure: false,
            lifetime: 0,
            regenerateInterval: SessionConfig::DEFAULT_REGENERATE_INTERVAL,
            cacheLimiter: null,
        );

        $handler = $this->realHandler();
        $response = $this->middleware($handler, $config)->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );

        $setCookie = $this->setCookieFor('Horde', $response);
        self::assertNotNull($setCookie);
        self::assertStringNotContainsString('Max-Age', $setCookie);
        self::assertStringNotContainsString('Expires', $setCookie);
    }

    #[Test]
    public function testSetCookieOmitsDomainWhenNull(): void
    {
        // cookieDomain: null tells the browser to scope the cookie to
        // the exact request host. The Domain= attribute must be
        // absent on the wire.
        $config = new SessionConfig(
            cookieName: 'Horde',
            cookieDomain: null,
            cookiePath: '/',
            secure: false,
            lifetime: 0,
            regenerateInterval: SessionConfig::DEFAULT_REGENERATE_INTERVAL,
            cacheLimiter: null,
        );

        $handler = $this->realHandler();
        $response = $this->middleware($handler, $config)->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );

        $setCookie = $this->setCookieFor('Horde', $response);
        self::assertNotNull($setCookie);
        self::assertStringNotContainsString('Domain=', $setCookie);
    }

    #[Test]
    public function testSetCookieOmitsSecureWhenSecureIsFalse(): void
    {
        $config = new SessionConfig(
            cookieName: 'Horde',
            cookieDomain: null,
            cookiePath: '/',
            secure: false,
            lifetime: 0,
            regenerateInterval: SessionConfig::DEFAULT_REGENERATE_INTERVAL,
            cacheLimiter: null,
        );

        $handler = $this->realHandler();
        $response = $this->middleware($handler, $config)->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );

        $setCookie = $this->setCookieFor('Horde', $response);
        self::assertNotNull($setCookie);
        self::assertStringNotContainsString('Secure', $setCookie);
    }
}

/**
 * Test handler that marks the loaded session destroyed and returns a
 * fixed response. Named (not anonymous) so DI / autoloader rules are
 * satisfied.
 */
final class MarkDestroyedHandler implements \Psr\Http\Server\RequestHandlerInterface
{
    public function __construct(
        private readonly \Psr\Http\Message\ResponseInterface $response,
    ) {}

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $session = $request->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
        if ($session instanceof HordeSession) {
            $session->markDestroyed();
        }
        return $this->response;
    }
}

/**
 * Test handler that schedules session-id regeneration and returns a
 * fixed response.
 */
final class ScheduleRegenerationHandler implements \Psr\Http\Server\RequestHandlerInterface
{
    public function __construct(
        private readonly \Psr\Http\Message\ResponseInterface $response,
    ) {}

    public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $session = $request->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
        if ($session instanceof HordeSession) {
            $session->scheduleRegeneration();
        }
        return $this->response;
    }
}
