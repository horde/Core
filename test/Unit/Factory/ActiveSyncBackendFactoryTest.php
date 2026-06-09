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

use Horde\Core\Test\Support\MockSkipConstructorTrait;
use Horde\Http\ServerRequest;
use Horde_ActiveSync_State_Sql;
use Horde_Auth_Base;
use Horde_Cache;
use Horde_Core_ActiveSync_Driver;
use Horde_Core_Factory_ActiveSyncBackend;
use Horde_Core_Factory_Auth;
use Horde_Exception_NotFound;
use Horde_Injector;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
    use MockSkipConstructorTrait;

    /**
     * Test that factory passes ServerRequest to driver
     */
    public function testFactoryPassesServerRequestToDriver(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

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

        $mockAuth = $this->getMockSkipConstructor(Horde_Auth_Base::class);
        $mockAuthFactory = $this->getMockSkipConstructor(Horde_Core_Factory_Auth::class);
        $mockAuthFactory->method('create')->willReturn($mockAuth);

        $mockInjector = $this->getMockSkipConstructor(Horde_Injector::class);

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
        $mockInjector->method('getInstance')
            ->willReturnCallback(function ($class) use ($mockAuthFactory) {
                if ($class === 'Horde_Core_Factory_Auth') {
                    return $mockAuthFactory;
                }
                throw new Horde_Exception_NotFound();
            });

        $GLOBALS['injector'] = $mockInjector;

        try {
            $factory = new Horde_Core_Factory_ActiveSyncBackend($mockInjector);
            $driver = $factory->create($mockInjector);

            $this->assertInstanceOf(Horde_Core_ActiveSync_Driver::class, $driver);

            $registry->expects($this->once())
                ->method('getAuth')
                ->willReturn('');

            $driver->getUser();
        } finally {
            unset($GLOBALS['injector']);
        }
    }

    /**
     * Test that factory creates ServerRequest from globals if not in injector
     */
    public function testFactoryCreatesServerRequestFromGlobalsAsFallback(): void
    {
        if (!function_exists('getallheaders')) {
            $this->markTestSkipped('getallheaders() not available in CLI SAPI');
        }

        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

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
        $registry->method('getAuth')->willReturn('test_user');

        $mockAuth = $this->getMockSkipConstructor(Horde_Auth_Base::class);
        $mockAuthFactory = $this->getMockSkipConstructor(Horde_Core_Factory_Auth::class);
        $mockAuthFactory->method('create')->willReturn($mockAuth);

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
        $mockInjector->method('getInstance')
            ->willReturnCallback(function ($class) use ($mockAuthFactory) {
                if ($class === 'Horde_Core_Factory_Auth') {
                    return $mockAuthFactory;
                }
                throw new Horde_Exception_NotFound();
            });

        $GLOBALS['injector'] = $mockInjector;

        try {
            $factory = new Horde_Core_Factory_ActiveSyncBackend($mockInjector);
            $driver = $factory->create($mockInjector);

            $this->assertInstanceOf(Horde_Core_ActiveSync_Driver::class, $driver);
        } finally {
            unset($GLOBALS['injector']);
        }
    }

    /**
     * Test that factory passes registry to driver
     */
    public function testFactoryPassesRegistryToDriver(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

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

        $mockAuth = $this->getMockSkipConstructor(Horde_Auth_Base::class);
        $mockAuthFactory = $this->getMockSkipConstructor(Horde_Core_Factory_Auth::class);
        $mockAuthFactory->method('create')->willReturn($mockAuth);

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
        $mockInjector->method('getInstance')
            ->willReturnCallback(function ($class) use ($mockAuthFactory) {
                if ($class === 'Horde_Core_Factory_Auth') {
                    return $mockAuthFactory;
                }
                throw new Horde_Exception_NotFound();
            });

        $GLOBALS['injector'] = $mockInjector;

        try {
            $factory = new Horde_Core_Factory_ActiveSyncBackend($mockInjector);
            $driver = $factory->create($mockInjector);

            $this->assertEquals('registry_user', $driver->getUser());
        } finally {
            unset($GLOBALS['injector']);
        }
    }
}
