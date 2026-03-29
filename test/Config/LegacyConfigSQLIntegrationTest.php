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
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use Horde_Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Config::class)]
class LegacyConfigSQLIntegrationTest extends TestCase
{
    private Injector $injector;
    private DriverRepository $repository;

    protected function setUp(): void
    {
        // Enable metadata system feature switch
        $GLOBALS['conf']['config']['use_metadata'] = true;

        // Set up dependency injection
        $this->injector = new Injector(new TopLevel());
        $this->repository = new DriverRepository();
        $this->repository->register(new MySQLDriver());

        // Bind ConfigMetadataProvider with our test repository
        $provider = new ConfigMetadataProvider($this->repository);
        $this->injector->setInstance(ConfigMetadataProvider::class, $provider);
    }

    public function testConfigSQLWithInjectorParameter(): void
    {
        $config = new Horde_Config('test', $this->injector);
        $result = $config->configSQL('sql|params');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('switch', $result);

        // Should have horde and custom switches
        $this->assertArrayHasKey('horde', $result['switch']);
        $this->assertArrayHasKey('custom', $result['switch']);

        // Custom switch should have phptype
        $this->assertArrayHasKey('fields', $result['switch']['custom']);
        $this->assertArrayHasKey('phptype', $result['switch']['custom']['fields']);

        // phptype should have a switch with SQL drivers
        $phptype = $result['switch']['custom']['fields']['phptype'];
        $this->assertArrayHasKey('switch', $phptype);
        $this->assertArrayHasKey('mysql', $phptype['switch']);

        // false option should exist for "None"
        $this->assertArrayHasKey('false', $phptype['switch']);
        $this->assertEquals('[None]', $phptype['switch']['false']['desc']);
    }

    public function testConfigSQLWithoutInjectorFallsBackToGlobal(): void
    {
        // Set up global injector
        $GLOBALS['injector'] = $this->injector;

        try {
            $config = new Horde_Config('test');
            $result = $config->configSQL('sql|params');

            $this->assertIsArray($result);
            $this->assertArrayHasKey('switch', $result);

            // Custom switch should have phptype with drivers
            $phptype = $result['switch']['custom']['fields']['phptype'];
            $this->assertArrayHasKey('mysql', $phptype['switch']);
        } finally {
            unset($GLOBALS['injector']);
        }
    }

    public function testConfigSQLDriversHaveCorrectStructure(): void
    {
        $config = new Horde_Config('test', $this->injector);
        $result = $config->configSQL('sql|params');

        $phptype = $result['switch']['custom']['fields']['phptype'];
        $mysqlConfig = $phptype['switch']['mysql'];

        // Should have standard SQL fields
        $this->assertArrayHasKey('username', $mysqlConfig);
        $this->assertArrayHasKey('password', $mysqlConfig);
        $this->assertArrayHasKey('database', $mysqlConfig);
        $this->assertArrayHasKey('charset', $mysqlConfig);
        $this->assertArrayHasKey('protocol', $mysqlConfig);

        // Username field structure
        $this->assertArrayHasKey('desc', $mysqlConfig['username']);
        $this->assertArrayHasKey('type', $mysqlConfig['username']);
        $this->assertArrayHasKey('required', $mysqlConfig['username']);
        $this->assertEquals('text', $mysqlConfig['username']['type']);
        $this->assertTrue($mysqlConfig['username']['required']);
    }

    public function testConfigSQLConditionalFieldsWork(): void
    {
        $config = new Horde_Config('test', $this->injector);
        $result = $config->configSQL('sql|params');

        $phptype = $result['switch']['custom']['fields']['phptype'];
        $mysqlConfig = $phptype['switch']['mysql'];

        // Protocol is a switch field
        $protocol = $mysqlConfig['protocol'];
        $this->assertEquals('switch', $protocol['type']);
        $this->assertArrayHasKey('fields', $protocol);

        // TCP case
        $this->assertArrayHasKey('tcp', $protocol['fields']);
        $tcpFields = $protocol['fields']['tcp'];
        $this->assertArrayHasKey('hostspec', $tcpFields);
        $this->assertArrayHasKey('port', $tcpFields);

        // Unix socket case
        $this->assertArrayHasKey('unix', $protocol['fields']);
        $unixFields = $protocol['fields']['unix'];
        $this->assertArrayHasKey('socket', $unixFields);

        // Unix should NOT have hostspec/port
        $this->assertArrayNotHasKey('hostspec', $unixFields);
        $this->assertArrayNotHasKey('port', $unixFields);
    }

    public function testConfigSQLMultipleDriversRegistered(): void
    {
        $config = new Horde_Config('test', $this->injector);
        $result = $config->configSQL('sql|params');

        $phptype = $result['switch']['custom']['fields']['phptype'];

        // MySQL should be present
        $this->assertArrayHasKey('mysql', $phptype['switch']);

        // Should have configuration
        $this->assertIsArray($phptype['switch']['mysql']);

        // Should have username field
        $this->assertArrayHasKey('username', $phptype['switch']['mysql']);
    }

    public function testConfigSQLWithoutInjectorFallsBackGracefully(): void
    {
        // No injector set anywhere
        if (isset($GLOBALS['injector'])) {
            $backup = $GLOBALS['injector'];
            unset($GLOBALS['injector']);
        }

        try {
            $config = new Horde_Config('test');
            $result = $config->configSQL('sql|params');

            // Should still return a valid structure (legacy fallback)
            $this->assertIsArray($result);
            $this->assertArrayHasKey('switch', $result);
        } finally {
            if (isset($backup)) {
                $GLOBALS['injector'] = $backup;
            }
        }
    }

    public function testConfigSQLTypeConsistency(): void
    {
        $config = new Horde_Config('test', $this->injector);
        $result = $config->configSQL('sql|params');

        $phptype = $result['switch']['custom']['fields']['phptype'];

        // Verify all drivers have consistent required fields
        $requiredFields = ['username', 'protocol', 'database', 'charset'];

        foreach (['mysql'] as $driver) {
            if (!isset($phptype['switch'][$driver])) {
                continue;
            }

            $driverConfig = $phptype['switch'][$driver];

            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $driverConfig,
                    "Driver {$driver} missing required field {$field}"
                );
            }
        }
    }

    public function testConfigSQLDefaultValues(): void
    {
        $config = new Horde_Config('test', $this->injector);
        $result = $config->configSQL('sql|params');

        $phptype = $result['switch']['custom']['fields']['phptype'];
        $mysqlConfig = $phptype['switch']['mysql'];

        // Charset should have default value
        $this->assertArrayHasKey('default', $mysqlConfig['charset']);
        $this->assertEquals('utf8mb4', $mysqlConfig['charset']['default']);

        // Protocol should have default
        $this->assertArrayHasKey('default', $mysqlConfig['protocol']);
        $this->assertEquals('tcp', $mysqlConfig['protocol']['default']);

        // Port in TCP should have default
        $tcpFields = $mysqlConfig['protocol']['fields']['tcp'];
        $this->assertArrayHasKey('default', $tcpFields['port']);
        $this->assertEquals('3306', $tcpFields['port']['default']);
    }
}
