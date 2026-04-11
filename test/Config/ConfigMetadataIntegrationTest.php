<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Config;

use Horde\Core\Config\ConfigMetadataProvider;
use Horde\Core\Config\ConfigStateWithMetadata;
use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\Driver\Sql\MySQLDriver;
use Horde\Core\Config\Legacy\LegacyConfigAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the config metadata system.
 *
 * Tests the complete flow from drivers through provider to state.
 * @coversNothing
 */
class ConfigMetadataIntegrationTest extends TestCase
{
    private DriverRepository $repository;
    private ConfigMetadataProvider $provider;

    protected function setUp(): void
    {
        $this->repository = new DriverRepository();
        $this->repository->register(new MySQLDriver());
        $this->provider = new ConfigMetadataProvider($this->repository);
    }

    public function testCompleteMetadataChain(): void
    {
        // 1. Get available drivers
        $drivers = $this->provider->getAvailableDrivers('sql');
        $this->assertArrayHasKey('mysql', $drivers);

        // 2. Get driver schema
        $schema = $this->provider->getDriverSchema('sql', 'mysql');
        $this->assertEquals('mysql', $schema['name']);
        $this->assertIsArray($schema['fields']);

        // 3. Validate configuration
        $config = [
            'username' => 'root',
            'protocol' => 'tcp',
            'hostspec' => 'localhost',
            'database' => 'horde',
            'charset' => 'utf8mb4',
        ];

        $result = $this->provider->validateConfig('sql', 'mysql', $config);
        $this->assertTrue($result->isValid());
    }

    public function testLegacyFormatConversion(): void
    {
        // Convert to legacy format
        $legacy = $this->provider->toLegacyFormat('sql', 'mysql');

        // Verify structure matches Horde_Config expectations
        $this->assertIsArray($legacy);
        $this->assertArrayHasKey('username', $legacy);
        $this->assertArrayHasKey('desc', $legacy['username']);
        $this->assertArrayHasKey('type', $legacy['username']);

        // Verify switch field with conditional fields
        $this->assertArrayHasKey('protocol', $legacy);
        $this->assertEquals('switch', $legacy['protocol']['type']);
        $this->assertArrayHasKey('fields', $legacy['protocol']);
    }

    public function testLegacyConfigAdapter(): void
    {
        $adapter = new LegacyConfigAdapter($this->provider);

        // Get legacy format via adapter
        $configSQL = $adapter->toConfigSQL();

        $this->assertIsArray($configSQL);
        $this->assertArrayHasKey('switch', $configSQL);
        $this->assertArrayHasKey('mysql', $configSQL['switch']);

        $mysqlConfig = $configSQL['switch']['mysql'];
        $this->assertArrayHasKey('username', $mysqlConfig);
    }

    public function testConfigStateWithMetadata(): void
    {
        $config = [
            'sql' => [
                'username' => 'root',
                'password' => 'secret',
                'protocol' => 'tcp',
                'hostspec' => 'localhost',
                'database' => 'horde',
                'charset' => 'utf8mb4',
            ],
        ];

        $state = new ConfigStateWithMetadata($config, $this->provider);

        // Test regular state access
        $this->assertEquals('root', $state->get('sql.username'));
        $this->assertEquals('horde', $state->get('sql.database'));

        // Test validation
        $result = $state->validate('sql', 'mysql');
        $this->assertTrue($result->isValid());
    }

    public function testValidationWithInvalidData(): void
    {
        $config = [
            'sql' => [
                'username' => 'root',
                // Missing required fields: protocol, database, charset
            ],
        ];

        $state = new ConfigStateWithMetadata($config, $this->provider);
        $result = $state->validate('sql', 'mysql');

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testJsonSerialization(): void
    {
        // Export complete schema
        $schema = $this->provider->exportSchema();

        // Should be JSON-serializable
        $json = json_encode($schema);
        $this->assertNotFalse($json);

        // Decode and verify structure
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('sql', $decoded);
        $this->assertArrayHasKey('mysql', $decoded['sql']);
    }
}
