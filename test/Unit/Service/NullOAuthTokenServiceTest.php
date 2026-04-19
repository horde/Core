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
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Service;

use Horde\Core\Service\Exception\OAuthTokenNotFoundException;
use Horde\Core\Service\NullOAuthTokenService;
use Horde\Core\Service\OAuthTokenService;
use Horde\Oauth\Client\TokenSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullOAuthTokenService::class)]
final class NullOAuthTokenServiceTest extends TestCase
{
    private NullOAuthTokenService $service;

    protected function setUp(): void
    {
        $this->service = new NullOAuthTokenService();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(OAuthTokenService::class, $this->service);
    }

    public function testHasTokensReturnsFalse(): void
    {
        self::assertFalse($this->service->hasTokens('user1', 'google'));
    }

    public function testStoreIsSilentlyAccepted(): void
    {
        $tokens = new TokenSet('access-token', 'Bearer');
        $this->service->store('user1', 'google', $tokens);
        self::assertFalse($this->service->hasTokens('user1', 'google'));
    }

    public function testRemoveIsSilentlyAccepted(): void
    {
        $this->service->remove('user1', 'google');
        self::assertFalse($this->service->hasTokens('user1', 'google'));
    }

    public function testGetAccessTokenThrows(): void
    {
        $this->expectException(OAuthTokenNotFoundException::class);
        $this->service->getAccessToken('user1', 'google');
    }

    public function testGetTokenSetThrows(): void
    {
        $this->expectException(OAuthTokenNotFoundException::class);
        $this->service->getTokenSet('user1', 'google');
    }
}
