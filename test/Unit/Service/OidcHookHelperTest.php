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
use Horde\Core\Service\OidcHookHelper;
use Horde\OAuth\Client\TokenSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OidcHookHelper::class)]
final class OidcHookHelperTest extends TestCase
{
    private OAuthTokenService $tokenService;
    private OAuthProviderConfigRepository $providerConfig;

    protected function setUp(): void
    {
        $this->tokenService   = $this->createStub(OAuthTokenService::class);
        $this->providerConfig = $this->createStub(OAuthProviderConfigRepository::class);
    }

    // ── findProviderForUser ───────────────────────────────────────────────────

    public function testFindProviderForUserReturnsNullWhenNoProviders(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([]);

        $result = OidcHookHelper::findProviderForUser(
            'testuser', $this->tokenService, $this->providerConfig
        );

        self::assertNull($result);
    }

    public function testFindProviderForUserReturnsNullWhenNoTokensExist(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([
            ['provider_id' => 'test-provider'],
        ]);
        $this->tokenService->method('hasTokens')->willReturn(false);

        $result = OidcHookHelper::findProviderForUser(
            'testuser', $this->tokenService, $this->providerConfig
        );

        self::assertNull($result);
    }

    public function testFindProviderForUserReturnsFirstMatchingProvider(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([
            ['provider_id' => 'provider-a'],
            ['provider_id' => 'provider-b'],
        ]);
        $this->tokenService->method('hasTokens')
            ->willReturnMap([
                ['testuser', 'provider-a', false],
                ['testuser', 'provider-b', true],
            ]);

        $result = OidcHookHelper::findProviderForUser(
            'testuser', $this->tokenService, $this->providerConfig
        );

        self::assertNotNull($result);
        self::assertSame('provider-b', $result['provider_id']);
    }

    public function testFindProviderForUserReturnsFirstWhenMultipleMatch(): void
    {
        $this->providerConfig->method('listEnabled')->willReturn([
            ['provider_id' => 'provider-a'],
            ['provider_id' => 'provider-b'],
        ]);
        $this->tokenService->method('hasTokens')->willReturn(true);

        $result = OidcHookHelper::findProviderForUser(
            'testuser', $this->tokenService, $this->providerConfig
        );

        self::assertSame('provider-a', $result['provider_id']);
    }

    // ── xoauth2Username ───────────────────────────────────────────────────────

    public function testXoauth2UsernameReturnsUsernameAsIsWhenUseEmailFalse(): void
    {
        $row = ['xoauth2_use_email' => false, 'xoauth2_domain' => 'example.org'];

        self::assertSame('testuser', OidcHookHelper::xoauth2Username('testuser', $row));
    }

    public function testXoauth2UsernameReturnsUsernameAsIsWhenDomainEmpty(): void
    {
        $row = ['xoauth2_use_email' => true, 'xoauth2_domain' => ''];

        self::assertSame('testuser', OidcHookHelper::xoauth2Username('testuser', $row));
    }

    public function testXoauth2UsernameAppendsDomainWhenConfigured(): void
    {
        $row = ['xoauth2_use_email' => true, 'xoauth2_domain' => 'example.org'];

        self::assertSame(
            'testuser@example.org',
            OidcHookHelper::xoauth2Username('testuser', $row)
        );
    }

    public function testXoauth2UsernameDoesNotAppendDomainIfAlreadyPresent(): void
    {
        $row = ['xoauth2_use_email' => true, 'xoauth2_domain' => 'example.org'];

        self::assertSame(
            'testuser@other.org',
            OidcHookHelper::xoauth2Username('testuser@other.org', $row)
        );
    }

    public function testXoauth2UsernameReturnsUsernameWhenRowEmpty(): void
    {
        self::assertSame('testuser', OidcHookHelper::xoauth2Username('testuser', []));
    }

    // ── getValidAccessToken — cases that do not trigger a refresh ─────────────

    public function testGetValidAccessTokenReturnsNullWhenGetTokenSetThrows(): void
    {
        $this->tokenService->method('getTokenSet')
            ->willThrowException(new \RuntimeException('not found'));

        $row = ['provider_id' => 'test-provider', 'token_endpoint' => 'https://idp.example.org/token'];
        $injector = $this->createMock(\Horde_Injector::class);

        $result = OidcHookHelper::getValidAccessToken(
            'testuser', $row, $this->tokenService, $injector
        );

        self::assertNull($result);
    }

    public function testGetValidAccessTokenReturnsTokenWhenNotExpired(): void
    {
        $tokenSet = new TokenSet(
            accessToken: 'valid-access-token',
            tokenType: 'Bearer',
            expiresIn: 3600,
        );

        $this->tokenService->method('getTokenSet')->willReturn($tokenSet);

        $row = ['provider_id' => 'test-provider'];
        $injector = $this->createMock(\Horde_Injector::class);

        $result = OidcHookHelper::getValidAccessToken(
            'testuser', $row, $this->tokenService, $injector
        );

        self::assertSame('valid-access-token', $result);
    }

    public function testGetValidAccessTokenReturnsNullWhenExpiredAndNoRefreshToken(): void
    {
        $tokenSet = new TokenSet(
            accessToken: 'expired-access-token',
            tokenType: 'Bearer',
            expiresIn: 0,
            receivedAt: time() - 3600,
        );

        $this->tokenService->method('getTokenSet')->willReturn($tokenSet);

        $row = ['provider_id' => 'test-provider'];
        $injector = $this->createMock(\Horde_Injector::class);

        $result = OidcHookHelper::getValidAccessToken(
            'testuser', $row, $this->tokenService, $injector
        );

        self::assertNull($result);
    }

    public function testGetValidAccessTokenReturnsNullWhenExpiredAndNoTokenEndpoint(): void
    {
        $tokenSet = new TokenSet(
            accessToken: 'expired-access-token',
            tokenType: 'Bearer',
            expiresIn: 0,
            refreshToken: 'refresh-token',
            receivedAt: time() - 3600,
        );

        $this->tokenService->method('getTokenSet')->willReturn($tokenSet);

        // No token_endpoint in row — ProviderConfig::fromArray will return null endpoint
        $row = ['provider_id' => 'test-provider'];
        $injector = $this->createMock(\Horde_Injector::class);

        $result = OidcHookHelper::getValidAccessToken(
            'testuser', $row, $this->tokenService, $injector
        );

        self::assertNull($result);
    }
}
