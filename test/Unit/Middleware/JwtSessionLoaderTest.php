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

use Horde\Core\Auth\Jwt\JwtService;
use Horde\Core\Auth\Jwt\VerifiedJwt;
use Horde\Core\Middleware\JwtSessionLoader;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\HordeSessionFactory;
use Horde\SessionHandler\Exception\SessionException;
use Horde\SessionHandler\SerializedSessionPayload;
use Horde\SessionHandler\SessionHandler;
use Horde\SessionHandler\SessionId;
use Horde\SessionHandler\SessionStorageBackend;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(JwtSessionLoader::class)]
class JwtSessionLoaderTest extends TestCase
{
    use SetUpTrait {
        setUp as protected traitSetUp;
    }

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->traitSetUp();
        $this->logger = new NullLogger();
    }

    /**
     * Build the middleware with the given collaborators. Each test
     * supplies fresh mocks/stubs tailored to what that test asserts.
     */
    private function middleware(
        JwtService $jwt,
        SessionStorageBackend $backend,
    ): JwtSessionLoader {
        $handler = new SessionHandler(
            $backend,
            sessionFactory: new HordeSessionFactory(),
        );
        return new JwtSessionLoader($jwt, $handler, $this->logger);
    }

    /** Build a request with the given cookies. */
    private function requestWithCookies(array $cookies)
    {
        $request = $this->requestFactory->createServerRequest('GET', '/test');
        return $request->withCookieParams($cookies);
    }

    /** Stub VerifiedJwt with the given jti claim. */
    private function verifiedJwtWithJti(string $jti): VerifiedJwt
    {
        $verified = $this->createStub(VerifiedJwt::class);
        $verified->method('getClaim')->willReturnCallback(
            static fn(string $name, mixed $default = null): mixed
                => $name === 'jti' ? $jti : $default,
        );
        return $verified;
    }

    // ---------------------------------------------------------------
    // No cookie -> pass through, no attribute set
    // ---------------------------------------------------------------

    #[Test]
    public function testNoCookieLeavesAttributeUnset(): void
    {
        $jwt = $this->createMock(JwtService::class);
        $jwt->expects(self::never())->method('verifyRefreshToken');

        $backend = $this->createMock(SessionStorageBackend::class);
        $backend->expects(self::never())->method('load');

        $response = $this->middleware($jwt, $backend)->process(
            $this->requestWithCookies([]),
            $this->defaultPayloadHandler,
        );

        self::assertSame($this->defaultPayloadResponse, $response);
        self::assertNull(
            $this->recentlyHandledRequest->getAttribute(JwtSessionLoader::ATTRIBUTE_SESSION),
        );
    }

    #[Test]
    public function testEmptyCookieLeavesAttributeUnset(): void
    {
        $jwt = $this->createMock(JwtService::class);
        $jwt->expects(self::never())->method('verifyRefreshToken');

        $backend = $this->createStub(SessionStorageBackend::class);

        $this->middleware($jwt, $backend)->process(
            $this->requestWithCookies([JwtSessionLoader::COOKIE_NAME => '']),
            $this->defaultPayloadHandler,
        );

        self::assertNull(
            $this->recentlyHandledRequest->getAttribute(JwtSessionLoader::ATTRIBUTE_SESSION),
        );
    }

    // ---------------------------------------------------------------
    // Verification failure -> pass through, no attribute set
    // ---------------------------------------------------------------

    #[Test]
    public function testInvalidJwtLeavesAttributeUnset(): void
    {
        $jwt = $this->createMock(JwtService::class);
        $jwt->expects(self::once())
            ->method('verifyRefreshToken')
            ->with('bad-token')
            ->willThrowException(new InvalidArgumentException('expired'));

        $backend = $this->createMock(SessionStorageBackend::class);
        $backend->expects(self::never())->method('load');

        $this->middleware($jwt, $backend)->process(
            $this->requestWithCookies([JwtSessionLoader::COOKIE_NAME => 'bad-token']),
            $this->defaultPayloadHandler,
        );

        self::assertNull(
            $this->recentlyHandledRequest->getAttribute(JwtSessionLoader::ATTRIBUTE_SESSION),
        );
    }

    // ---------------------------------------------------------------
    // Missing jti claim -> pass through, no attribute set
    // ---------------------------------------------------------------

    #[Test]
    public function testJwtWithoutJtiClaimLeavesAttributeUnset(): void
    {
        $verified = $this->createStub(VerifiedJwt::class);
        $verified->method('getClaim')->willReturn(null);

        $jwt = $this->createMock(JwtService::class);
        $jwt->expects(self::once())->method('verifyRefreshToken')->willReturn($verified);

        $backend = $this->createMock(SessionStorageBackend::class);
        $backend->expects(self::never())->method('load');

        $this->middleware($jwt, $backend)->process(
            $this->requestWithCookies([JwtSessionLoader::COOKIE_NAME => 'token']),
            $this->defaultPayloadHandler,
        );

        self::assertNull(
            $this->recentlyHandledRequest->getAttribute(JwtSessionLoader::ATTRIBUTE_SESSION),
        );
    }

    // ---------------------------------------------------------------
    // Session row missing -> pass through, no attribute set
    // ---------------------------------------------------------------

    #[Test]
    public function testNullSessionRowLeavesAttributeUnset(): void
    {
        $jwt = $this->createStub(JwtService::class);
        $jwt->method('verifyRefreshToken')
            ->willReturn($this->verifiedJwtWithJti('jti-xyz'));

        $backend = $this->createMock(SessionStorageBackend::class);
        $backend->expects(self::once())->method('load')->willReturn(null);

        $this->middleware($jwt, $backend)->process(
            $this->requestWithCookies([JwtSessionLoader::COOKIE_NAME => 'token']),
            $this->defaultPayloadHandler,
        );

        self::assertNull(
            $this->recentlyHandledRequest->getAttribute(JwtSessionLoader::ATTRIBUTE_SESSION),
        );
    }

    // ---------------------------------------------------------------
    // Session backend error -> pass through, no attribute set
    // ---------------------------------------------------------------

    #[Test]
    public function testSessionLoadExceptionLeavesAttributeUnset(): void
    {
        $jwt = $this->createStub(JwtService::class);
        $jwt->method('verifyRefreshToken')
            ->willReturn($this->verifiedJwtWithJti('jti-xyz'));

        $backend = $this->createMock(SessionStorageBackend::class);
        $backend->expects(self::once())->method('load')
            ->willThrowException(new SessionException('backend down'));

        $this->middleware($jwt, $backend)->process(
            $this->requestWithCookies([JwtSessionLoader::COOKIE_NAME => 'token']),
            $this->defaultPayloadHandler,
        );

        self::assertNull(
            $this->recentlyHandledRequest->getAttribute(JwtSessionLoader::ATTRIBUTE_SESSION),
        );
    }

    // ---------------------------------------------------------------
    // Happy path -> session attribute is the loaded HordeSession
    // ---------------------------------------------------------------

    #[Test]
    public function testValidJwtAndSessionRowSetsAttribute(): void
    {
        $jti = 'jti-abc-123';
        $serialised = new SerializedSessionPayload(
            serialize(['horde' => ['auth/userId' => 'alice']])
        );

        $jwt = $this->createMock(JwtService::class);
        $jwt->expects(self::once())
            ->method('verifyRefreshToken')
            ->with('token')
            ->willReturn($this->verifiedJwtWithJti($jti));

        $backend = $this->createMock(SessionStorageBackend::class);
        $backend->expects(self::once())->method('load')->willReturnCallback(
            static fn(SessionId $id): ?SerializedSessionPayload
                => (string) $id === $jti ? $serialised : null,
        );

        $this->middleware($jwt, $backend)->process(
            $this->requestWithCookies([JwtSessionLoader::COOKIE_NAME => 'token']),
            $this->defaultPayloadHandler,
        );

        $session = $this->recentlyHandledRequest
            ->getAttribute(JwtSessionLoader::ATTRIBUTE_SESSION);

        self::assertInstanceOf(HordeSession::class, $session);
        self::assertSame('alice', $session->getAuthenticatedUser());
    }

    #[Test]
    public function testNeverShortCircuitsTheRequest(): void
    {
        // Whatever happens, the inner handler must run. The middleware
        // is a session-loader, not an auth gate.
        $jwt = $this->createStub(JwtService::class);
        $jwt->method('verifyRefreshToken')
            ->willThrowException(new InvalidArgumentException('expired'));

        $backend = $this->createStub(SessionStorageBackend::class);

        $response = $this->middleware($jwt, $backend)->process(
            $this->requestWithCookies([JwtSessionLoader::COOKIE_NAME => 'bad']),
            $this->defaultPayloadHandler,
        );

        self::assertSame($this->defaultPayloadResponse, $response);
        self::assertNotNull($this->recentlyHandledRequest);
    }
}
