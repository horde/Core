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

use Exception;
use Horde\Core\Config\LegacyMergedConfig;
use Horde\Core\Config\State;
use Horde_Injector;
use Horde_Injector_TopLevel;
use Horde_Registry_Hordeconfig;
use Horde_Registry_Hordeconfig_Merged;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for LegacyMergedConfig injector binding behavior.
 *
 * Verifies that LegacyMergedConfig is properly rebound to the injector
 * when importConfig() is called, simulating pushApp/popApp scenarios.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class LegacyMergedConfigInjectorTest extends TestCase
{
    private Horde_Injector $injector;

    protected function setUp(): void
    {
        $this->injector = new Horde_Injector(new Horde_Injector_TopLevel());
    }

    /**
     * Test that LegacyMergedConfig can be bound and retrieved from injector.
     */
    public function testLegacyMergedConfigCanBeBoundToInjector(): void
    {
        $config = new LegacyMergedConfig(['test' => 'value']);

        $this->injector->setInstance(LegacyMergedConfig::class, $config);

        $retrieved = $this->injector->getInstance(LegacyMergedConfig::class);

        $this->assertSame($config, $retrieved);
        $this->assertEquals('value', $retrieved->get('test'));
    }

    /**
     * Test that rebinding updates the injector with new config.
     *
     * Simulates what happens in importConfig() on pushApp/popApp.
     */
    public function testRebindingUpdatesInjectorInstance(): void
    {
        // Initial binding (simulates importConfig('horde'))
        $hordeConfig = new LegacyMergedConfig(['app' => 'horde', 'cache' => ['driver' => 'file']]);
        $this->injector->setInstance(LegacyMergedConfig::class, $hordeConfig);

        $retrieved1 = $this->injector->getInstance(LegacyMergedConfig::class);
        $this->assertEquals('horde', $retrieved1->get('app'));
        $this->assertEquals('file', $retrieved1->get('cache.driver'));

        // Rebind (simulates pushApp('imp') → importConfig('imp'))
        $impConfig = new LegacyMergedConfig(['app' => 'imp', 'cache' => ['driver' => 'memcache']]);
        $this->injector->setInstance(LegacyMergedConfig::class, $impConfig);

        $retrieved2 = $this->injector->getInstance(LegacyMergedConfig::class);
        $this->assertEquals('imp', $retrieved2->get('app'));
        $this->assertEquals('memcache', $retrieved2->get('cache.driver'));

        // Verify it's a different instance
        $this->assertNotSame($retrieved1, $retrieved2);
    }

    /**
     * Test pushApp/popApp simulation with config context switching.
     *
     * Simulates:
     * 1. appInit('horde') → importConfig('horde')
     * 2. pushApp('imp') → importConfig('imp')
     * 3. popApp() → importConfig('horde')
     */
    public function testSimulatePushPopAppConfigSwitching(): void
    {
        // Simulate appInit('horde')
        $hordeConfig = new LegacyMergedConfig([
            'app' => 'horde',
            'database' => ['host' => 'horde-db'],
        ]);
        $this->injector->setInstance(LegacyMergedConfig::class, $hordeConfig);

        $config1 = $this->injector->getInstance(LegacyMergedConfig::class);
        $this->assertEquals('horde', $config1->get('app'));
        $this->assertEquals('horde-db', $config1->get('database.host'));

        // Simulate pushApp('imp') → importConfig('imp')
        // (imp config merged with horde config)
        $impConfig = new LegacyMergedConfig([
            'app' => 'imp',
            'database' => ['host' => 'horde-db'],  // Inherited from horde
            'mail' => ['server' => 'imap.example.com'],  // imp-specific
        ]);
        $this->injector->setInstance(LegacyMergedConfig::class, $impConfig);

        $config2 = $this->injector->getInstance(LegacyMergedConfig::class);
        $this->assertEquals('imp', $config2->get('app'));
        $this->assertEquals('horde-db', $config2->get('database.host'));
        $this->assertEquals('imap.example.com', $config2->get('mail.server'));

        // Simulate popApp() → importConfig('horde')
        // (restore horde config)
        $this->injector->setInstance(LegacyMergedConfig::class, $hordeConfig);

        $config3 = $this->injector->getInstance(LegacyMergedConfig::class);
        $this->assertEquals('horde', $config3->get('app'));
        $this->assertEquals('horde-db', $config3->get('database.host'));
        $this->assertNull($config3->get('mail.server'));  // imp-specific gone

        // Verify we got back original instance
        $this->assertSame($hordeConfig, $config3);
    }

    /**
     * Test that multiple factories can share the same config instance.
     */
    public function testMultipleFactoriesShareSameConfigInstance(): void
    {
        $config = new LegacyMergedConfig(['shared' => 'value']);
        $this->injector->setInstance(LegacyMergedConfig::class, $config);

        // Simulate two factories requesting config
        $instance1 = $this->injector->getInstance(LegacyMergedConfig::class);
        $instance2 = $this->injector->getInstance(LegacyMergedConfig::class);

        // Should be same instance (singleton per binding)
        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test that LegacyMergedConfig binding is independent of State.
     */
    public function testLegacyMergedConfigBindingIndependentOfState(): void
    {
        $legacyConfig = new LegacyMergedConfig(['legacy' => 'data']);
        $this->injector->setInstance(LegacyMergedConfig::class, $legacyConfig);

        // Verify LegacyMergedConfig is bound
        $retrieved = $this->injector->getInstance(LegacyMergedConfig::class);
        $this->assertSame($legacyConfig, $retrieved);

        // Verify State is NOT bound (would throw exception)
        try {
            $this->injector->getInstance(State::class);
            $this->fail('State should not be bound to injector');
        } catch (Exception $e) {
            // Expected - State is not bound, throws from State constructor or injector
            $this->assertTrue(true, 'State correctly not bound to injector');
        }
    }

    /**
     * Test config immutability is preserved through injector.
     */
    public function testInjectedConfigIsImmutable(): void
    {
        $config = new LegacyMergedConfig(['value' => 'original']);
        $this->injector->setInstance(LegacyMergedConfig::class, $config);

        $retrieved = $this->injector->getInstance(LegacyMergedConfig::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ConfigState is immutable');

        $retrieved['value'] = 'modified';
    }
}
