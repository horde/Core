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
use Horde\Core\Service\NullOAuthHttpClientService;
use Horde\Core\Service\OAuthHttpClientService;
use Horde\Core\Service\WantedScopes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullOAuthHttpClientService::class)]
final class NullOAuthHttpClientServiceTest extends TestCase
{
    private NullOAuthHttpClientService $service;

    protected function setUp(): void
    {
        $this->service = new NullOAuthHttpClientService();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(OAuthHttpClientService::class, $this->service);
    }

    public function testGetClientThrows(): void
    {
        $this->expectException(OAuthTokenNotFoundException::class);
        $this->service->getClient('user1', 'google');
    }

    public function testGetClientWithScopesThrows(): void
    {
        $this->expectException(OAuthTokenNotFoundException::class);
        $this->service->getClient('user1', 'google', WantedScopes::of('openid'));
    }
}
