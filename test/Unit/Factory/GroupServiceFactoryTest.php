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

use Horde\Core\Factory\GroupServiceFactory;
use Horde\Core\Factory\LdapGroupServiceFactory;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\Core\Service\LdapGroupService;
use Horde\Injector\Injector;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * Tests for GroupServiceFactory
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(GroupServiceFactory::class)]
class GroupServiceFactoryTest extends TestCase
{
    /**
     * @param array<string, mixed> $conf
     */
    private function makeInjector(array $conf, ?LdapGroupServiceFactory $ldapGroupServiceFactory = null): Injector
    {
        $configLoader = $this->createStub(ConfigLoader::class);
        $configLoader->method('load')->willReturn(new State($conf));

        $map = [[ConfigLoader::class, $configLoader]];
        if ($ldapGroupServiceFactory !== null) {
            $map[] = [LdapGroupServiceFactory::class, $ldapGroupServiceFactory];
        }

        $injector = $this->createStub(Injector::class);
        $injector->method('getInstance')->willReturnMap($map);

        return $injector;
    }

    public function testCreateLdapBackendDelegatesToLdapGroupServiceFactory(): void
    {
        $expectedService = $this->createStub(LdapGroupService::class);
        $ldapGroupServiceFactory = $this->createMock(LdapGroupServiceFactory::class);

        $injector = $this->makeInjector(
            ['group' => ['driver' => 'ldap', 'params' => ['basedn' => 'ou=group,dc=example,dc=com']]],
            $ldapGroupServiceFactory
        );

        $ldapGroupServiceFactory->expects($this->once())
            ->method('create')
            ->with($injector)
            ->willReturn($expectedService);

        $factory = new GroupServiceFactory();
        $result = $factory->create($injector);

        $this->assertSame($expectedService, $result);
    }

    public function testUnsupportedDriverThrows(): void
    {
        $injector = $this->makeInjector(['group' => ['driver' => 'file']]);

        $factory = new GroupServiceFactory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported group driver: file');

        $factory->create($injector);
    }
}
