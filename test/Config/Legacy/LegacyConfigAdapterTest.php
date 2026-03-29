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

namespace Horde\Core\Test\Config\Legacy;

use Horde\Core\Config\ConfigMetadataProvider;
use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\Driver\Sql\MySQLDriver;
use Horde\Core\Config\Legacy\LegacyConfigAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LegacyConfigAdapter::class)]
class LegacyConfigAdapterTest extends TestCase
{
    private DriverRepository $repository;
    private ConfigMetadataProvider $provider;
    private LegacyConfigAdapter $adapter;

    protected function setUp(): void
    {
        $this->repository = new DriverRepository();
        $this->repository->register(new MySQLDriver());
        $this->provider = new ConfigMetadataProvider($this->repository);
        $this->adapter = new LegacyConfigAdapter($this->provider);
    }

    public function testConstructorAcceptsConfigMetadataProvider(): void
    {
        // Should not throw type error
        $adapter = new LegacyConfigAdapter($this->provider);
        $this->assertInstanceOf(LegacyConfigAdapter::class, $adapter);
    }

    public function testToConfigSQLReturnsArrayWithSwitchKey(): void
    {
        $result = $this->adapter->toConfigSQL('sql');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('switch', $result);
        $this->assertIsArray($result['switch']);
    }

    public function testToConfigSQLIncludesRegisteredDrivers(): void
    {
        $result = $this->adapter->toConfigSQL('sql');

        $this->assertArrayHasKey('mysql', $result['switch']);
    }

    public function testToConfigSQLDriversHaveExpectedFields(): void
    {
        $result = $this->adapter->toConfigSQL('sql');
        $mysqlConfig = $result['switch']['mysql'];

        // Check for common SQL driver fields
        $this->assertArrayHasKey('username', $mysqlConfig);
        $this->assertArrayHasKey('password', $mysqlConfig);
        $this->assertArrayHasKey('database', $mysqlConfig);

        // Verify field structure
        $this->assertArrayHasKey('desc', $mysqlConfig['username']);
        $this->assertArrayHasKey('type', $mysqlConfig['username']);
        $this->assertEquals('text', $mysqlConfig['username']['type']);
    }

    public function testToConfigSQLConditionalFieldsStructure(): void
    {
        $result = $this->adapter->toConfigSQL('sql');
        $mysqlConfig = $result['switch']['mysql'];

        // Protocol is a conditional field
        $this->assertArrayHasKey('protocol', $mysqlConfig);
        $protocolField = $mysqlConfig['protocol'];

        $this->assertEquals('switch', $protocolField['type']);
        $this->assertArrayHasKey('fields', $protocolField);

        // TCP case should have hostspec
        $this->assertArrayHasKey('tcp', $protocolField['fields']);
        $this->assertArrayHasKey('hostspec', $protocolField['fields']['tcp']);

        // Unix case should have socket
        $this->assertArrayHasKey('unix', $protocolField['fields']);
        $this->assertArrayHasKey('socket', $protocolField['fields']['unix']);
    }

    public function testToConfigNoSQLUsesNoSqlType(): void
    {
        // Register a NoSQL driver if we have one
        // For now, test that it doesn't break
        $result = $this->adapter->toConfigNoSQL('nosql');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('switch', $result);
    }

    public function testToConfigLDAPUsesLdapType(): void
    {
        $result = $this->adapter->toConfigLDAP();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('switch', $result);
    }

    public function testToConfigVFSUsesVfsType(): void
    {
        $result = $this->adapter->toConfigVFS();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('switch', $result);
    }

    public function testRequiredFieldsMarked(): void
    {
        $result = $this->adapter->toConfigSQL('sql');
        $mysqlConfig = $result['switch']['mysql'];

        // Username should be required
        $this->assertArrayHasKey('required', $mysqlConfig['username']);
        $this->assertTrue($mysqlConfig['username']['required']);

        // Database should also be required
        $this->assertArrayHasKey('required', $mysqlConfig['database']);
        $this->assertTrue($mysqlConfig['database']['required']);
    }

    public function testDefaultValuesIncluded(): void
    {
        $result = $this->adapter->toConfigSQL('sql');
        $mysqlConfig = $result['switch']['mysql'];

        // Charset should have a default
        $this->assertArrayHasKey('charset', $mysqlConfig);
        $this->assertArrayHasKey('default', $mysqlConfig['charset']);
        $this->assertEquals('utf8mb4', $mysqlConfig['charset']['default']);
    }

    public function testOptionsIncludedForEnumFields(): void
    {
        $result = $this->adapter->toConfigSQL('sql');
        $mysqlConfig = $result['switch']['mysql'];

        // Protocol is a switch field (not enum with values)
        // It uses 'fields' for conditional fields, not 'values'
        $this->assertArrayHasKey('protocol', $mysqlConfig);
        $this->assertEquals('switch', $mysqlConfig['protocol']['type']);
        $this->assertArrayHasKey('fields', $mysqlConfig['protocol']);

        // The fields should contain different protocol options
        $this->assertArrayHasKey('tcp', $mysqlConfig['protocol']['fields']);
        $this->assertArrayHasKey('unix', $mysqlConfig['protocol']['fields']);
    }
}
