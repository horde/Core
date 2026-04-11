<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 * @subpackage UnitTests
 */

namespace Horde\Core\Test\Unit\Factory;

use Horde\Test\TestCase;
use Horde\Http\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde_Core_Factory_ActiveSyncBackend;
use Horde_Injector;
use Horde_Registry;
use Horde_ActiveSync_State_Sql;
use Horde_Cache;
use Horde_Exception_NotFound;
use Horde_Core_ActiveSync_Driver;

/**
 * Unit tests for Horde_Core_Factory_ActiveSyncBackend
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 * @subpackage UnitTests
 */
#[CoversClass(Horde_Core_Factory_ActiveSyncBackend::class)]
class ActiveSyncBackendFactoryTest extends TestCase
{
    /**
     * Test that factory passes ServerRequest to driver
     */
    public function testFactoryPassesServerRequestToDriver(): void
    {
        global $conf, $registry;

        // Setup minimal global config
        $conf = [
            'activesync' => [
                'emailsync' => false,
                'ping' => [],
                'auth' => ['type' => 'basic'],
            ],
        ];

        // Mock registry
        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->method('hasInterface')->willReturn(false);

        // Mock injector
        $mockInjector = $this->getMockSkipConstructor(Horde_Injector::class);

        // Setup ServerRequest in injector
        $serverRequest = new ServerRequest('POST', '/Microsoft-Server-ActiveSync');
        $mockInjector->method('get')
            ->willReturnCallback(function ($class) use ($serverRequest) {
                if ($class === ServerRequest::class) {
                    return $serverRequest;
                }
                if ($class === 'Horde_ActiveSyncState') {
                    return $this->getMockSkipConstructor(Horde_ActiveSync_State_Sql::class);
                }
                if ($class === 'Horde_Cache') {
                    return $this->getMockSkipConstructor(Horde_Cache::class);
                }
                throw new Horde_Exception_NotFound();
            });

        $factory = new Horde_Core_Factory_ActiveSyncBackend($mockInjector);
        $driver = $factory->create($mockInjector);

        $this->assertInstanceOf(Horde_Core_ActiveSync_Driver::class, $driver);

        // Verify driver can access dependencies (getUser should work)
        // Without authentication or GET params, should fall back to registry
        $registry->expects($this->once())
            ->method('getAuth')
            ->willReturn('');

        $driver->getUser();
    }

    /**
     * Test that factory creates ServerRequest from globals if not in injector
     */
    public function testFactoryCreatesServerRequestFromGlobalsAsFallback(): void
    {
        global $conf, $registry;

        // Setup minimal global config
        $conf = [
            'activesync' => [
                'emailsync' => false,
                'ping' => [],
                'auth' => ['type' => 'basic'],
            ],
        ];

        // Mock registry
        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->method('hasInterface')->willReturn(false);
        $registry->method('getAuth')->willReturn('test_user');

        // Mock injector that doesn't have ServerRequest
        $mockInjector = $this->getMockSkipConstructor(Horde_Injector::class);

        $mockInjector->method('get')
            ->willReturnCallback(function ($class) {
                if ($class === ServerRequest::class) {
                    throw new Horde_Exception_NotFound('Not found');
                }
                if ($class === 'Horde_ActiveSyncState') {
                    return $this->getMockSkipConstructor(Horde_ActiveSync_State_Sql::class);
                }
                if ($class === 'Horde_Cache') {
                    return $this->getMockSkipConstructor(Horde_Cache::class);
                }
                throw new Horde_Exception_NotFound();
            });

        $factory = new Horde_Core_Factory_ActiveSyncBackend($mockInjector);
        $driver = $factory->create($mockInjector);

        $this->assertInstanceOf(Horde_Core_ActiveSync_Driver::class, $driver);
    }

    /**
     * Test that factory passes registry to driver
     */
    public function testFactoryPassesRegistryToDriver(): void
    {
        global $conf, $registry;

        $conf = [
            'activesync' => [
                'emailsync' => false,
                'ping' => [],
                'auth' => ['type' => 'basic'],
            ],
        ];

        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->method('hasInterface')->willReturn(false);
        $registry->expects($this->once())
            ->method('getAuth')
            ->willReturn('registry_user');

        $mockInjector = $this->getMockSkipConstructor(Horde_Injector::class);

        $serverRequest = new ServerRequest('POST', '/');
        $mockInjector->method('get')
            ->willReturnCallback(function ($class) use ($serverRequest) {
                if ($class === ServerRequest::class) {
                    return $serverRequest;
                }
                if ($class === 'Horde_ActiveSyncState') {
                    return $this->getMockSkipConstructor(Horde_ActiveSync_State_Sql::class);
                }
                if ($class === 'Horde_Cache') {
                    return $this->getMockSkipConstructor(Horde_Cache::class);
                }
                throw new Horde_Exception_NotFound();
            });

        $factory = new Horde_Core_Factory_ActiveSyncBackend($mockInjector);
        $driver = $factory->create($mockInjector);

        // Verify registry is used
        $this->assertEquals('registry_user', $driver->getUser());
    }
}
