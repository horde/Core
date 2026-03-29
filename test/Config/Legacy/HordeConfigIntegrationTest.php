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

namespace Horde\Core\Test\Config\Legacy;

use Horde\Core\Config\ConfigMetadataProvider;
use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\Driver\Sql\PostgreSQLDriver;
use Horde\Core\Config\Driver\Sql\SQLiteDriver;
use Horde\Core\Config\Driver\Auth\SqlAuthDriver;
use Horde\Core\Config\Driver\Auth\LdapAuthDriver;
use Horde\Core\Config\Legacy\LegacyConfigAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Integration test with Horde_Config legacy format.
 *
 * Validates that the legacy format actually works with real Horde_Config
 * expectations, focusing on:
 * - PostgreSQL driver (has nested ENUM in conditionalFields - the reported bug)
 * - SQLite driver (has ENUM at top level)
 * - Auth drivers (have ENUM fields)
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class HordeConfigIntegrationTest extends TestCase
{
    /**
     * Test PostgreSQL driver with nested ENUM in conditionalFields.
     *
     * This is the exact scenario from the bug report:
     * - ssl: SWITCH field
     * - sslmode: ENUM field nested inside ssl conditionalFields
     *
     * The bug: "Unknown named parameter $allowedValues"
     */
    public function testPostgreSQLDriverWithNestedEnum(): void
    {
        $repository = new DriverRepository();
        $repository->register(new PostgreSQLDriver());

        $provider = new ConfigMetadataProvider($repository);
        $adapter = new LegacyConfigAdapter($provider);

        // This should not throw "Unknown named parameter"
        $configSQL = $adapter->toConfigSQL();

        $this->assertArrayHasKey('switch', $configSQL);
        $this->assertArrayHasKey('pgsql', $configSQL['switch']);

        $pgsqlConfig = $configSQL['switch']['pgsql'];

        // Verify ssl switch field exists
        $this->assertArrayHasKey('ssl', $pgsqlConfig);
        $this->assertEquals('switch', $pgsqlConfig['ssl']['type']);
        $this->assertEquals('Use SSL to connect to the server?', $pgsqlConfig['ssl']['desc']);

        // CRITICAL: Verify nested conditional fields
        $this->assertArrayHasKey(
            'fields',
            $pgsqlConfig['ssl'],
            "SSL switch field should have 'fields' for conditional fields"
        );

        $this->assertArrayHasKey(
            'true',
            $pgsqlConfig['ssl']['fields'],
            "SSL switch should have 'true' case"
        );

        // CRITICAL: Verify nested sslmode ENUM field
        $this->assertArrayHasKey(
            'sslmode',
            $pgsqlConfig['ssl']['fields']['true'],
            "SSL 'true' case should have 'sslmode' field - this is the bug!"
        );

        $sslmodeField = $pgsqlConfig['ssl']['fields']['true']['sslmode'];

        $this->assertEquals('enum', $sslmodeField['type']);
        $this->assertEquals('SSL mode', $sslmodeField['desc']);

        // CRITICAL: The actual bug - missing 'values' for nested ENUM
        $this->assertArrayHasKey(
            'values',
            $sslmodeField,
            "Nested sslmode ENUM field MUST have 'values' key - THIS IS THE REPORTED BUG!"
        );

        // Verify values are associative array
        $this->assertIsArray($sslmodeField['values']);
        $this->assertNotEmpty($sslmodeField['values']);

        // Verify it's associative (not simple array)
        $this->assertArrayHasKey('require', $sslmodeField['values']);
        $this->assertArrayHasKey('disable', $sslmodeField['values']);

        // Verify format is correct for dropdown rendering
        $this->assertIsString($sslmodeField['values']['require']);
    }

    /**
     * Test SQLite driver with top-level ENUM field.
     */
    public function testSQLiteDriverWithEnum(): void
    {
        $repository = new DriverRepository();
        $repository->register(new SQLiteDriver());

        $provider = new ConfigMetadataProvider($repository);

        $legacy = $provider->toLegacyFormat('sql', 'sqlite');

        // Verify mode ENUM field
        $this->assertArrayHasKey('mode', $legacy);
        $this->assertEquals('enum', $legacy['mode']['type']);

        // CRITICAL: Must have 'values' key
        $this->assertArrayHasKey(
            'values',
            $legacy['mode'],
            "SQLite mode ENUM field must have 'values' key"
        );

        // Verify values are associative
        $this->assertIsArray($legacy['mode']['values']);
        $this->assertArrayHasKey('0600', $legacy['mode']['values']);
    }

    /**
     * Test Auth SQL driver with ENUM field.
     */
    public function testAuthSqlDriverWithEnum(): void
    {
        $repository = new DriverRepository();
        $repository->register(new SqlAuthDriver());

        $provider = new ConfigMetadataProvider($repository);

        $legacy = $provider->toLegacyFormat('auth', 'sql');

        // Verify encryption ENUM field
        $this->assertArrayHasKey('encryption', $legacy);
        $this->assertEquals('enum', $legacy['encryption']['type']);

        // CRITICAL: Must have 'values' key
        $this->assertArrayHasKey(
            'values',
            $legacy['encryption'],
            "Auth SQL encryption ENUM field must have 'values' key"
        );

        // Verify values are associative
        $this->assertIsArray($legacy['encryption']['values']);
        $this->assertNotEmpty($legacy['encryption']['values']);

        // Should have common encryption methods
        $this->assertArrayHasKey('ssha', $legacy['encryption']['values']);
    }

    /**
     * Test Auth LDAP driver with ENUM field.
     */
    public function testAuthLdapDriverWithEnum(): void
    {
        $repository = new DriverRepository();
        $repository->register(new LdapAuthDriver());

        $provider = new ConfigMetadataProvider($repository);

        $legacy = $provider->toLegacyFormat('auth', 'ldap');

        // Verify encryption ENUM field
        $this->assertArrayHasKey('encryption', $legacy);
        $this->assertEquals('enum', $legacy['encryption']['type']);

        // CRITICAL: Must have 'values' key
        $this->assertArrayHasKey(
            'values',
            $legacy['encryption'],
            "Auth LDAP encryption ENUM field must have 'values' key"
        );

        $this->assertIsArray($legacy['encryption']['values']);
    }

    /**
     * Test that configSQL adapter method works end-to-end.
     */
    public function testConfigSQLAdapterEndToEnd(): void
    {
        $repository = new DriverRepository();
        $repository->register(new PostgreSQLDriver());
        $repository->register(new SQLiteDriver());

        $provider = new ConfigMetadataProvider($repository);
        $adapter = new LegacyConfigAdapter($provider);

        // This is what Horde_Config calls
        $configSQL = $adapter->toConfigSQL();

        // Verify top-level structure
        $this->assertIsArray($configSQL);
        $this->assertArrayHasKey('switch', $configSQL);

        // Verify both drivers are present
        $this->assertArrayHasKey('pgsql', $configSQL['switch']);
        $this->assertArrayHasKey('sqlite', $configSQL['switch']);

        // Verify each driver has proper fields
        foreach (['pgsql', 'sqlite'] as $driver) {
            $driverConfig = $configSQL['switch'][$driver];

            $this->assertIsArray($driverConfig);
            $this->assertNotEmpty($driverConfig);

            // Every field should have desc and type
            foreach ($driverConfig as $fieldName => $fieldConfig) {
                $this->assertArrayHasKey(
                    'desc',
                    $fieldConfig,
                    "Field {$driver}:{$fieldName} missing 'desc'"
                );

                $this->assertArrayHasKey(
                    'type',
                    $fieldConfig,
                    "Field {$driver}:{$fieldName} missing 'type'"
                );
            }
        }
    }
}
