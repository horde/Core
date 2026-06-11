<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Session;

use Horde\Core\Config\State;
use Horde\Core\Session\SessionConfig;
use Horde\Core\Session\SessionConfigFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionConfigFactory::class)]
#[CoversClass(SessionConfig::class)]
class SessionConfigFactoryTest extends TestCase
{
    private SessionConfigFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SessionConfigFactory();
    }

    // ---------------------------------------------------------------
    // Defaults applied when keys are absent
    // ---------------------------------------------------------------

    #[Test]
    public function testEmptyStateProducesDefaultedConfig(): void
    {
        $config = $this->factory->fromState(new State([]));

        self::assertSame('', $config->cookieName);
        self::assertNull($config->cookieDomain);
        self::assertSame(SessionConfig::DEFAULT_COOKIE_PATH, $config->cookiePath);
        self::assertFalse($config->secure);
        self::assertSame(0, $config->lifetime);
        self::assertSame(SessionConfig::DEFAULT_REGENERATE_INTERVAL, $config->regenerateInterval);
        self::assertNull($config->cacheLimiter);
    }

    #[Test]
    public function testEmptyCookieDomainBecomesNull(): void
    {
        // An empty string in $conf['cookie']['domain'] means "host-only";
        // SessionConfig represents that as null so the cookie helpers can
        // omit the Domain attribute rather than emit Domain= with an
        // empty value.
        $config = $this->factory->fromState(new State([
            'cookie' => ['domain' => ''],
        ]));
        self::assertNull($config->cookieDomain);
    }

    #[Test]
    public function testEmptyCookiePathFallsBackToRoot(): void
    {
        $config = $this->factory->fromState(new State([
            'cookie' => ['path' => ''],
        ]));
        self::assertSame('/', $config->cookiePath);
    }

    #[Test]
    public function testZeroRegenerateIntervalFallsBackToDefault(): void
    {
        // Zero or negative regenerate intervals are not meaningful;
        // SessionLifecycle::regenerateInterval() applies the same fallback.
        $config = $this->factory->fromState(new State([
            'session' => ['regenerate_interval' => 0],
        ]));
        self::assertSame(SessionConfig::DEFAULT_REGENERATE_INTERVAL, $config->regenerateInterval);
    }

    #[Test]
    public function testNonIntegerRegenerateIntervalFallsBackToDefault(): void
    {
        $config = $this->factory->fromState(new State([
            'session' => ['regenerate_interval' => 'lots'],
        ]));
        self::assertSame(SessionConfig::DEFAULT_REGENERATE_INTERVAL, $config->regenerateInterval);
    }

    #[Test]
    public function testEmptyCacheLimiterBecomesNull(): void
    {
        $config = $this->factory->fromState(new State([
            'session' => ['cache_limiter' => ''],
        ]));
        self::assertNull($config->cacheLimiter);
    }

    // ---------------------------------------------------------------
    // Field-by-field translation
    // ---------------------------------------------------------------

    #[Test]
    public function testAllFieldsRoundTrip(): void
    {
        $config = $this->factory->fromState(new State([
            'session' => [
                'name' => 'Horde',
                'timeout' => 3600,
                'cache_limiter' => 'nocache',
                'regenerate_interval' => 7200,
            ],
            'cookie' => [
                'domain' => 'example.com',
                'path' => '/horde',
            ],
            'use_ssl' => 1,
        ]));

        self::assertSame('Horde', $config->cookieName);
        self::assertSame('example.com', $config->cookieDomain);
        self::assertSame('/horde', $config->cookiePath);
        self::assertTrue($config->secure);
        self::assertSame(3600, $config->lifetime);
        self::assertSame(7200, $config->regenerateInterval);
        self::assertSame('nocache', $config->cacheLimiter);
    }

    #[Test]
    public function testUseSslZeroIsNotSecure(): void
    {
        $config = $this->factory->fromState(new State([
            'use_ssl' => 0,
        ]));
        self::assertFalse($config->secure);
    }

    #[Test]
    public function testUseSslTwoIsNotSecure(): void
    {
        // Legacy `use_ssl` permits values 0/1/2 where 2 means "auto-detect"
        // not "always force SSL". Only 1 is a forced HTTPS configuration.
        $config = $this->factory->fromState(new State([
            'use_ssl' => 2,
        ]));
        self::assertFalse($config->secure);
    }

    // ---------------------------------------------------------------
    // Immutability
    // ---------------------------------------------------------------

    #[Test]
    public function testConfigIsImmutableValueObject(): void
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

        // readonly properties enforce this at the engine level; the
        // assertion is documentary.
        self::assertSame('Horde', $config->cookieName);
    }
}
