<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Config;

use Horde\Core\Config\LegacyMergedConfig;
use Horde\Core\Config\State;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for LegacyMergedConfig.
 *
 * Verifies that LegacyMergedConfig properly extends State and can be
 * used as an injectable config type separate from State.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(LegacyMergedConfig::class)]
class LegacyMergedConfigTest extends TestCase
{
    public function testExtendsState(): void
    {
        $config = new LegacyMergedConfig(['key' => 'value']);

        $this->assertInstanceOf(State::class, $config);
        $this->assertInstanceOf(LegacyMergedConfig::class, $config);
    }

    public function testInheritsStateGetMethod(): void
    {
        $config = new LegacyMergedConfig([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);

        $this->assertEquals('localhost', $config->get('database.host'));
        $this->assertEquals(3306, $config->get('database.port'));
        $this->assertNull($config->get('database.username'));
    }

    public function testInheritsStateHasMethod(): void
    {
        $config = new LegacyMergedConfig(['key' => 'value']);

        $this->assertTrue($config->has('key'));
        $this->assertFalse($config->has('nonexistent'));
    }

    public function testInheritsStateToArrayMethod(): void
    {
        $data = ['cache' => ['driver' => 'memcache'], 'debug' => true];
        $config = new LegacyMergedConfig($data);

        $this->assertEquals($data, $config->toArray());
    }

    public function testInheritsStateArrayAccess(): void
    {
        $config = new LegacyMergedConfig(['session' => ['timeout' => 3600]]);

        $this->assertTrue(isset($config['session']));
        $this->assertEquals(['timeout' => 3600], $config['session']);
        $this->assertNull($config['nonexistent']);
    }

    public function testInheritsStateImmutability(): void
    {
        $config = new LegacyMergedConfig(['key' => 'value']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ConfigState is immutable');

        $config['key'] = 'modified';
    }

    public function testCanBeDistinguishedFromState(): void
    {
        $state = new State(['data' => 'state']);
        $legacy = new LegacyMergedConfig(['data' => 'legacy']);

        // Both are State instances
        $this->assertInstanceOf(State::class, $state);
        $this->assertInstanceOf(State::class, $legacy);

        // But only legacy is LegacyMergedConfig
        $this->assertNotInstanceOf(LegacyMergedConfig::class, $state);
        $this->assertInstanceOf(LegacyMergedConfig::class, $legacy);
    }

    public function testSupportsTypedInjection(): void
    {
        // This test demonstrates that LegacyMergedConfig can be used
        // as a distinct type hint in dependency injection
        $legacy = new LegacyMergedConfig(['injected' => true]);

        // Type checking works
        $this->assertInstanceOf(LegacyMergedConfig::class, $legacy);

        // Can be used in type hints without accepting generic State
        $acceptsLegacy = function (LegacyMergedConfig $config): bool {
            return $config->get('injected') === true;
        };

        $this->assertTrue($acceptsLegacy($legacy));
    }
}
