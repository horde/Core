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
use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\Driver\Sql\MySQLDriver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigMetadataProvider::class)]
class ConfigMetadataProviderTest extends TestCase
{
    private DriverRepository $repository;
    private ConfigMetadataProvider $provider;

    protected function setUp(): void
    {
        $this->repository = new DriverRepository();
        $this->repository->register(new MySQLDriver());
        $this->provider = new ConfigMetadataProvider($this->repository);
    }

    public function testGetAvailableDrivers(): void
    {
        $drivers = $this->provider->getAvailableDrivers('sql');

        $this->assertIsArray($drivers);
        $this->assertArrayHasKey('mysql', $drivers);
        $this->assertEquals('MySQL / PDO', $drivers['mysql']);
    }

    public function testGetAvailableDriversReturnsEmptyForUnknownType(): void
    {
        $drivers = $this->provider->getAvailableDrivers('nosql');

        $this->assertIsArray($drivers);
        $this->assertEmpty($drivers);
    }

    public function testGetDriverSchema(): void
    {
        $schema = $this->provider->getDriverSchema('sql', 'mysql');

        $this->assertIsArray($schema);
        $this->assertEquals('mysql', $schema['name']);
        $this->assertEquals('MySQL / PDO', $schema['description']);
        $this->assertEquals('sql', $schema['type']);
        $this->assertArrayHasKey('fields', $schema);
        $this->assertIsArray($schema['fields']);

        // Verify some expected fields
        $fieldNames = array_column($schema['fields'], 'name');
        $this->assertContains('username', $fieldNames);
        $this->assertContains('password', $fieldNames);
        $this->assertContains('database', $fieldNames);
    }

    public function testGetDriverSchemaThrowsExceptionForUnknownDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver not found: sql/unknown');

        $this->provider->getDriverSchema('sql', 'unknown');
    }

    public function testValidateConfigWithValidData(): void
    {
        $config = [
            'username' => 'testuser',
            'password' => 'testpass',
            'protocol' => 'tcp',
            'hostspec' => 'localhost',
            'database' => 'testdb',
            'charset' => 'utf8mb4',
        ];

        $result = $this->provider->validateConfig('sql', 'mysql', $config);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateConfigWithMissingRequiredFields(): void
    {
        $config = [
            'username' => 'testuser',
            // Missing: protocol, database, charset
        ];

        $result = $this->provider->validateConfig('sql', 'mysql', $config);

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertNotEmpty($errors);

        // Check for specific missing fields
        $errorText = implode(' ', $errors);
        $this->assertStringContainsString('protocol', $errorText);
        $this->assertStringContainsString('database', $errorText);
        $this->assertStringContainsString('charset', $errorText);
    }

    public function testExportSchema(): void
    {
        $schema = $this->provider->exportSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('sql', $schema);
        $this->assertArrayHasKey('mysql', $schema['sql']);

        $mysqlSchema = $schema['sql']['mysql'];
        $this->assertEquals('mysql', $mysqlSchema['name']);
        $this->assertIsArray($mysqlSchema['fields']);
    }

    public function testToLegacyFormat(): void
    {
        $legacy = $this->provider->toLegacyFormat('sql', 'mysql');

        $this->assertIsArray($legacy);
        $this->assertArrayHasKey('username', $legacy);
        $this->assertArrayHasKey('password', $legacy);
        $this->assertArrayHasKey('database', $legacy);

        // Check structure of a field
        $usernameField = $legacy['username'];
        $this->assertArrayHasKey('desc', $usernameField);
        $this->assertArrayHasKey('type', $usernameField);
        $this->assertEquals('text', $usernameField['type']);
        $this->assertTrue($usernameField['required']);
    }

    public function testToLegacyFormatWithConditionalFields(): void
    {
        $legacy = $this->provider->toLegacyFormat('sql', 'mysql');

        // Protocol is a switch field with conditional fields
        $this->assertArrayHasKey('protocol', $legacy);
        $protocolField = $legacy['protocol'];

        $this->assertEquals('switch', $protocolField['type']);
        $this->assertArrayHasKey('fields', $protocolField);

        // Check tcp case has hostspec
        $this->assertArrayHasKey('tcp', $protocolField['fields']);
        $this->assertArrayHasKey('hostspec', $protocolField['fields']['tcp']);

        // Check unix case has socket
        $this->assertArrayHasKey('unix', $protocolField['fields']);
        $this->assertArrayHasKey('socket', $protocolField['fields']['unix']);
    }
}
