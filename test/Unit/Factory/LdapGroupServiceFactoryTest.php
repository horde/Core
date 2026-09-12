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
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Factory;

use Horde\Core\Factory\LdapGroupServiceFactory;
use Horde\Core\Factory\HordeLdapServiceFactory;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\Core\Service\StandardHordeLdapService;
use Horde\Core\Service\LdapGroupService;
use Horde\Injector\Injector;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * Tests for LdapGroupServiceFactory
 *
 * @requires extension ldap
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(LdapGroupServiceFactory::class)]
class LdapGroupServiceFactoryTest extends TestCase
{
    /**
     * @param array<string, mixed> $conf
     */
    private function makeInjector(array $conf, ?HordeLdapServiceFactory $ldapServiceFactory = null): Injector
    {
        $configLoader = $this->createStub(ConfigLoader::class);
        $configLoader->method('load')->willReturn(new State($conf));

        $map = [[ConfigLoader::class, $configLoader]];
        if ($ldapServiceFactory !== null) {
            $map[] = [HordeLdapServiceFactory::class, $ldapServiceFactory];
        }

        $injector = $this->createStub(Injector::class);
        $injector->method('getInstance')->willReturnMap($map);

        return $injector;
    }

    public function testUsesGroupsSpecificLdapConnection(): void
    {
        $ldapService = $this->createStub(StandardHordeLdapService::class);
        $ldapServiceFactory = $this->createMock(HordeLdapServiceFactory::class);

        $injector = $this->makeInjector([
            'group' => ['params' => [
                'basedn' => 'ou=group,dc=example,dc=com',
                'gid' => 'cn',
                'memberuid' => 'memberUid',
                'search' => ['objectclass' => ['posixGroup']],
                'newgroup_objectclass' => ['posixGroup', 'hordeGroup'],
            ]],
        ], $ldapServiceFactory);

        $ldapServiceFactory->expects($this->once())
            ->method('create')
            ->with($injector, 'horde:groups')
            ->willReturn($ldapService);

        $factory = new LdapGroupServiceFactory();
        $result = $factory->create($injector);

        $this->assertInstanceOf(LdapGroupService::class, $result);
    }

    public function testMissingBasednThrows(): void
    {
        $ldapServiceFactory = $this->createStub(HordeLdapServiceFactory::class);
        $ldapServiceFactory->method('create')->willReturn($this->createStub(StandardHordeLdapService::class));

        $injector = $this->makeInjector([
            'group' => ['params' => ['gid' => 'cn']], // no basedn
        ], $ldapServiceFactory);

        $factory = new LdapGroupServiceFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('basedn');

        $factory->create($injector);
    }
}
