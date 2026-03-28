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

namespace Horde\Core\Test\Factory;

use Horde\Core\Factory\HordeLdapServiceFactory;
use Horde\Core\Service\StandardHordeLdapService;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde_Cache;
use Horde_Injector;
use Horde_Ldap;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for HordeLdapServiceFactory
 *
 * @requires extension ldap
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(HordeLdapServiceFactory::class)]
class HordeLdapServiceFactoryTest extends TestCase
{
    private ConfigLoader $configLoader;
    private Horde_Injector $injector;
    private HordeLdapServiceFactory $factory;

    protected function setUp(): void
    {
        $this->configLoader = $this->createMock(ConfigLoader::class);
        $this->injector = $this->createMock(Horde_Injector::class);
        $this->injector->method('getInstance')
            ->willReturnCallback(function ($class) {
                if ($class === ConfigLoader::class) {
                    return $this->configLoader;
                }
                throw new \Exception("Unknown dependency: $class");
            });

        $this->factory = new HordeLdapServiceFactory();
    }

    public function testCreateDefaultService(): void
    {
        $config = [
            'hostspec' => 'ldap.example.com',
            'port' => 389,
            'basedn' => 'dc=example,dc=com',
        ];

        $mockState = $this->createMock(State::class);
        $mockState->method('has')->willReturn(true);
        $mockState->method('get')->with('ldap')->willReturn($config);

        $this->configLoader->method('load')->with('horde')->willReturn($mockState);

        $service = $this->factory->create($this->injector, 'horde');

        $this->assertInstanceOf(StandardHordeLdapService::class, $service);
        $this->assertInstanceOf(Horde_Ldap::class, $service->getAdapter());
    }

    public function testCreateServiceSpecific(): void
    {
        $defaultConfig = [
            'hostspec' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
        ];

        $groupsConfig = [
            'hostspec' => 'ldap-groups.example.com',
            'basedn' => 'ou=groups,dc=example,dc=com',
        ];

        $mockState = $this->createMock(State::class);
        $mockState->method('has')->willReturnCallback(function ($key) {
            return in_array($key, ['ldap', 'ldap.service.groups']);
        });
        $mockState->method('get')->willReturnCallback(function ($key) use ($defaultConfig, $groupsConfig) {
            return $key === 'ldap.service.groups' ? $groupsConfig : $defaultConfig;
        });

        $this->configLoader->method('load')->with('horde')->willReturn($mockState);

        $service = $this->factory->create($this->injector, 'horde:groups');

        $this->assertInstanceOf(StandardHordeLdapService::class, $service);
        $this->assertInstanceOf(Horde_Ldap::class, $service->getAdapter());
    }

    public function testConnectionPooling(): void
    {
        $config = [
            'hostspec' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
        ];

        $mockState = $this->createMock(State::class);
        $mockState->method('has')->willReturn(true);
        $mockState->method('get')->with('ldap')->willReturn($config);

        $this->configLoader->method('load')->with('horde')->willReturn($mockState);

        $service1 = $this->factory->create($this->injector, 'horde');
        $service2 = $this->factory->create($this->injector, 'horde');

        $this->assertSame(
            $service1->getAdapter(),
            $service2->getAdapter(),
            'Same config should return same adapter instance'
        );
    }

    public function testDifferentConfigsDifferentAdapters(): void
    {
        $config1 = [
            'hostspec' => 'ldap1.example.com',
            'basedn' => 'dc=example,dc=com',
        ];

        $config2 = [
            'hostspec' => 'ldap2.example.com',
            'basedn' => 'dc=mail,dc=example,dc=com',
        ];

        $mockState = $this->createMock(State::class);
        $mockState->method('has')->willReturnCallback(function ($key) {
            return in_array($key, ['ldap', 'ldap.service.groups']);
        });
        $mockState->method('get')->willReturnCallback(function ($key) use ($config1, $config2) {
            return $key === 'ldap.service.groups' ? $config2 : $config1;
        });

        $this->configLoader->method('load')->with('horde')->willReturn($mockState);

        $service1 = $this->factory->create($this->injector, 'horde');
        $service2 = $this->factory->create($this->injector, 'horde:groups');

        $this->assertNotSame(
            $service1->getAdapter(),
            $service2->getAdapter(),
            'Different configs should get different adapters'
        );
    }

    public function testMissingConfigThrows(): void
    {
        $mockState = $this->createMock(State::class);
        $mockState->method('has')->willReturn(false);

        $this->configLoader->method('load')->with('horde')->willReturn($mockState);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No LDAP configuration found');

        $this->factory->create($this->injector, 'horde');
    }

    public function testParseServiceIdSimple(): void
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseServiceId');
        $method->setAccessible(true);

        $result = $method->invoke($this->factory, 'horde');
        $this->assertEquals(['horde', null], $result);
    }

    public function testParseServiceIdWithService(): void
    {
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('parseServiceId');
        $method->setAccessible(true);

        $result = $method->invoke($this->factory, 'horde:groups');
        $this->assertEquals(['horde', 'groups'], $result);

        $result = $method->invoke($this->factory, 'imp:storage');
        $this->assertEquals(['imp', 'storage'], $result);
    }
}
