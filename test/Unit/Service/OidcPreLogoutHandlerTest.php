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
 * @author   Jean Charles Delépine <jean.charles.delepine@u-picardie.fr>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Service;

use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\OAuthTokenService;
use Horde\Core\Service\OidcPreLogoutHandler;
use Horde\Core\Service\PreLogoutHandlerInterface;
use Horde\Core\Uri\RouteUrlWriter;
use Horde_Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\NullLogger;

#[CoversClass(OidcPreLogoutHandler::class)]
final class OidcPreLogoutHandlerTest extends TestCase
{
    private OAuthProviderConfigRepository $providerConfig;
    private OAuthTokenService $tokenService;
    private RouteUrlWriter $urlWriter;
    private OidcPreLogoutHandler $handler;

    protected function setUp(): void
    {
        $this->providerConfig = $this->createStub(OAuthProviderConfigRepository::class);
        $this->tokenService   = $this->createStub(OAuthTokenService::class);
        $this->urlWriter      = $this->createStub(RouteUrlWriter::class);

        $this->urlWriter->method('absoluteUrlFor')
             ->willReturn('https://horde.example.org/horde/services/portal/');

        $this->handler = new OidcPreLogoutHandler(
            providerConfig:  $this->providerConfig,
            tokenService:    $this->tokenService,
            urlWriter:       $this->urlWriter,
            httpClient:      $this->createStub(ClientInterface::class),
            requestFactory:  $this->createStub(RequestFactoryInterface::class),
            streamFactory:   $this->createStub(StreamFactoryInterface::class),
            logger:          new NullLogger(),
        );
    }

    private function makeHandler(
        OAuthProviderConfigRepository $providerConfig,
        OAuthTokenService $tokenService,
    ): OidcPreLogoutHandler {
        return new OidcPreLogoutHandler(
            providerConfig:  $providerConfig,
            tokenService:    $tokenService,
            urlWriter:       $this->urlWriter,
            httpClient:      $this->createStub(ClientInterface::class),
            requestFactory:  $this->createStub(RequestFactoryInterface::class),
            streamFactory:   $this->createStub(StreamFactoryInterface::class),
            logger:          new NullLogger(),
        );
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(PreLogoutHandlerInterface::class, $this->handler);
    }

    public function testReturnsEmptyArrayOnSessionExpiry(): void
    {
        // REASON_SESSION must be ignored — only voluntary logouts are handled
        $providerConfig = $this->createMock(OAuthProviderConfigRepository::class);
        $providerConfig->expects(self::never())->method('listEnabled');
        $handler = $this->makeHandler($providerConfig, $this->tokenService);

        $result = $handler->onBeforeLogout('testuser', Horde_Auth::REASON_SESSION);

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayWhenNoTokensExist(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([
            ['provider_id' => 'test-provider', 'logout_type' => 'local'],
        ]);
        $this->tokenService->method('hasTokens')->willReturn(false);

        $result = $this->handler->onBeforeLogout('testuser', Horde_Auth::REASON_LOGOUT);

        self::assertSame([], $result);
    }

    public function testLocalStrategyRemovesTokensAndReturnsEmpty(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([
            ['provider_id' => 'test-provider', 'logout_type' => 'local'],
        ]);
        $this->tokenService->method('hasTokens')->willReturn(true);

        $tokenService = $this->createMock(OAuthTokenService::class);
        $tokenService->method('hasTokens')->willReturn(true);
        $tokenService->expects(self::once())
            ->method('remove')
            ->with('testuser', 'test-provider');

        $handler = $this->makeHandler($this->providerConfig, $tokenService);
        $result = $handler->onBeforeLogout('testuser', Horde_Auth::REASON_LOGOUT);

        self::assertSame([], $result);
    }

    public function testDefaultStrategyIsLocal(): void
    {
        // logout_type absent — defaults to 'local'
        $this->providerConfig->method('listEnabled')->willReturn([
            ['provider_id' => 'test-provider'],
        ]);

        $tokenService = $this->createMock(OAuthTokenService::class);
        $tokenService->method('hasTokens')->willReturn(true);
        $tokenService->expects(self::once())->method('remove');

        $handler = $this->makeHandler($this->providerConfig, $tokenService);
        $result = $handler->onBeforeLogout('testuser', Horde_Auth::REASON_LOGOUT);

        self::assertSame([], $result);
    }

    public function testSloStrategyReturnsRedirectWithConfiguredEndpoint(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([[
            'provider_id'              => 'test-provider',
            'logout_type'              => 'slo',
            'end_session_endpoint'     => 'https://idp.example.org/oidc/logout',
            'post_logout_redirect_uri' => 'https://horde.example.org/horde/login.php',
        ]]);
        $this->tokenService->method('hasTokens')->willReturn(true);

        $result = $this->handler->onBeforeLogout('testuser', Horde_Auth::REASON_LOGOUT);

        self::assertArrayHasKey('redirect', $result);
        self::assertStringStartsWith('https://idp.example.org/oidc/logout', $result['redirect']);
        self::assertStringContainsString('post_logout_redirect_uri=', $result['redirect']);
        self::assertStringContainsString(
            urlencode('https://horde.example.org/horde/login.php'),
            $result['redirect']
        );
    }

    public function testSloStrategyFallsBackToPortalWhenNoPostLogoutUri(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([[
            'provider_id'          => 'test-provider',
            'logout_type'          => 'slo',
            'end_session_endpoint' => 'https://idp.example.org/oidc/logout',
        ]]);
        $this->tokenService->method('hasTokens')->willReturn(true);

        $result = $this->handler->onBeforeLogout('testuser', Horde_Auth::REASON_LOGOUT);

        self::assertArrayHasKey('redirect', $result);
        self::assertStringContainsString(
            urlencode('https://horde.example.org/horde/services/portal/'),
            $result['redirect']
        );
    }

    public function testSloStrategyReturnsEmptyWhenNoEndpointAvailable(): void
    {
        // No end_session_endpoint and no discoverable issuer
        $this->providerConfig->method('listEnabled')->willReturn([[
            'provider_id' => 'test-provider',
            'logout_type' => 'slo',
            'issuer'      => '',
        ]]);
        $this->tokenService->method('hasTokens')->willReturn(true);

        $result = $this->handler->onBeforeLogout('testuser', Horde_Auth::REASON_LOGOUT);

        self::assertSame([], $result);
    }

    public function testTokenRemovalFailureDoesNotPreventLogout(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([
            ['provider_id' => 'test-provider', 'logout_type' => 'local'],
        ]);
        $this->tokenService->method('hasTokens')->willReturn(true);
        $this->tokenService->method('remove')
            ->willThrowException(new \RuntimeException('DB error'));

        // Must not throw — failing handler must never prevent logout
        $result = $this->handler->onBeforeLogout('testuser', Horde_Auth::REASON_LOGOUT);

        self::assertIsArray($result);
    }

    public function testUsesFirstProviderWithTokens(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([
            ['provider_id' => 'provider-a', 'logout_type' => 'local'],
            ['provider_id' => 'provider-b', 'logout_type' => 'local'],
        ]);

        $tokenService = $this->createMock(OAuthTokenService::class);
        $tokenService->method('hasTokens')
            ->willReturnMap([
                ['testuser', 'provider-a', false],
                ['testuser', 'provider-b', true],
            ]);
        $tokenService->expects(self::once())
            ->method('remove')
            ->with('testuser', 'provider-b');

        $handler = $this->makeHandler($this->providerConfig, $tokenService);
        $handler->onBeforeLogout('testuser', Horde_Auth::REASON_LOGOUT);
    }

    public function testSepIsAmpersandWhenEndpointAlreadyHasQueryString(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([[
            'provider_id'          => 'test-provider',
            'logout_type'          => 'slo',
            'end_session_endpoint' => 'https://idp.example.org/oidc/logout?foo=bar',
        ]]);
        $this->tokenService->method('hasTokens')->willReturn(true);

        $result = $this->handler->onBeforeLogout('testuser', Horde_Auth::REASON_LOGOUT);

        self::assertStringContainsString(
            'https://idp.example.org/oidc/logout?foo=bar&post_logout_redirect_uri=',
            $result['redirect']
        );
    }
}
