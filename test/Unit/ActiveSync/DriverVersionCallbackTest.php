<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Torben Dannhauer
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 * @subpackage UnitTests
 */

namespace Horde\Core\Test\Unit\ActiveSync;

use Horde\Core\Test\Support\MockSkipConstructorTrait;
use Horde\Http\ServerRequest;
use Horde_ActiveSync;
use Horde_ActiveSync_Credentials;
use Horde_ActiveSync_Wbxml_Decoder;
use Horde_ActiveSync_Wbxml_Encoder;
use Horde_Controller_Request_Mock;
use Horde_Core_ActiveSync_Auth;
use Horde_Core_ActiveSync_Connector;
use Horde_Core_ActiveSync_Driver;
use Horde_Exception;
use Horde_Injector;
use Horde_Registry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Horde_Core_ActiveSync_Driver::versionCallback().
 *
 * Regression coverage for https://github.com/horde/Core/pull/103: Basic-auth
 * usernames must be read explicitly from Horde_ActiveSync_Credentials because
 * empty() does not invoke __get() when __isset() is absent.
 *
 * @author   Torben Dannhauer
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 * @subpackage UnitTests
 */
#[CoversMethod(Horde_Core_ActiveSync_Driver::class, 'versionCallback')]
class DriverVersionCallbackTest extends TestCase
{
    use MockSkipConstructorTrait;

    private array $savedGlobals = [];

    protected function tearDown(): void
    {
        foreach (['injector', 'registry', 'conf'] as $key) {
            if (array_key_exists($key, $this->savedGlobals)) {
                $GLOBALS[$key] = $this->savedGlobals[$key];
            } else {
                unset($GLOBALS[$key]);
            }
        }

        parent::tearDown();
    }

    /**
     * Documents the PHP quirk behind PR #103: Horde_ActiveSync_Credentials
     * returns a username via __get(), but empty($credentials->username) is
     * true without __isset() — so versionCallback() must not use empty().
     */
    public function testCredentialsUsernameNotDetectedByEmpty(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $server = $this->createActiveSyncServer([
            'PHP_AUTH_USER' => 'alice',
            'PHP_AUTH_PW' => 'secret',
        ]);
        $credentials = new Horde_ActiveSync_Credentials($server);

        $this->assertSame('alice', $credentials->username);
        $this->assertTrue(empty($credentials->username));
    }

    /**
     * With Basic-auth credentials present, versionCallback() resolves the
     * username, loads horde:activesync:version for that user, and applies it
     * to the server (here: lowers the ceiling from global 16.0 to 14.1).
     */
    public function testVersionCallbackAppliesPermissionFromBasicAuthUsername(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $server = $this->createActiveSyncServer(
            [
                'PHP_AUTH_USER' => 'alice',
                'PHP_AUTH_PW' => 'secret',
            ],
            [],
            Horde_ActiveSync::VERSION_SIXTEEN
        );
        $driver = $this->createDriver();
        $this->setupGlobals(
            permsVersion: Horde_ActiveSync::VERSION_FOURTEENONE,
            expectedUsername: 'alice',
            globalVersion: Horde_ActiveSync::VERSION_SIXTEEN
        );

        $driver->versionCallback($server);

        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_FOURTEENONE);
    }

    /**
     * Production order: factory sets global conf['activesync']['version']
     * (14.1), then versionCallback() runs. Per-user permission 16.0 must
     * raise MS-ASProtocolVersions above the admin default for this request.
     */
    public function testVersionCallbackRaisesSupportedVersionAboveGlobalSetting(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $server = $this->createActiveSyncServer(
            [
                'PHP_AUTH_USER' => 'alice',
                'PHP_AUTH_PW' => 'secret',
            ],
            [],
            Horde_ActiveSync::VERSION_FOURTEENONE
        );
        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_FOURTEENONE);

        $driver = $this->createDriver();
        $this->setupGlobals(
            permsVersion: Horde_ActiveSync::VERSION_SIXTEEN,
            expectedUsername: 'alice',
            globalVersion: Horde_ActiveSync::VERSION_FOURTEENONE
        );

        $driver->versionCallback($server);

        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_SIXTEEN);
    }

    /**
     * Per-user horde:activesync:version 16.1 must raise MS-ASProtocolVersions
     * above global 16.0 for this request.
     */
    public function testVersionCallbackAppliesSixteenOnePermission(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $server = $this->createActiveSyncServer(
            [
                'PHP_AUTH_USER' => 'alice',
                'PHP_AUTH_PW' => 'secret',
            ],
            [],
            Horde_ActiveSync::VERSION_SIXTEEN
        );
        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_SIXTEEN);

        $driver = $this->createDriver();
        $this->setupGlobals(
            permsVersion: Horde_ActiveSync::VERSION_SIXTEENONE,
            expectedUsername: 'alice',
            globalVersion: Horde_ActiveSync::VERSION_SIXTEEN
        );

        $driver->versionCallback($server);

        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_SIXTEENONE);
    }

    /**
     * Global admin ceiling is 16.0; per-user horde:activesync:version is
     * 14.1. versionCallback() must lower the advertised protocol versions for
     * this user below the global setting.
     */
    public function testVersionCallbackLowersSupportedVersionBelowGlobalSetting(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $server = $this->createActiveSyncServer(
            [
                'PHP_AUTH_USER' => 'alice',
                'PHP_AUTH_PW' => 'secret',
            ],
            [],
            Horde_ActiveSync::VERSION_SIXTEEN
        );
        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_SIXTEEN);

        $driver = $this->createDriver();
        $this->setupGlobals(
            permsVersion: Horde_ActiveSync::VERSION_FOURTEENONE,
            expectedUsername: 'alice',
            globalVersion: Horde_ActiveSync::VERSION_SIXTEEN
        );

        $driver->versionCallback($server);

        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_FOURTEENONE);
    }

    /**
     * Without Basic-auth headers, the ActiveSync User GET parameter is used
     * to resolve the principal and apply per-user version permissions (here:
     * global 14.1, user bob allowed 16.0).
     */
    public function testVersionCallbackFallsBackToGetUserParameter(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $server = $this->createActiveSyncServer(
            [],
            ['User' => 'bob', 'DeviceId' => 'DEVICE01'],
            Horde_ActiveSync::VERSION_FOURTEENONE
        );
        $driver = $this->createDriver();
        $this->setupGlobals(
            permsVersion: Horde_ActiveSync::VERSION_SIXTEEN,
            expectedUsername: 'bob',
            globalVersion: Horde_ActiveSync::VERSION_FOURTEENONE
        );

        $driver->versionCallback($server);

        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_SIXTEEN);
    }

    /**
     * If horde:activesync:version is not defined in the permission system,
     * versionCallback() must leave the global ceiling from setSupportedVersion()
     * unchanged.
     */
    public function testVersionCallbackSkipsWhenPermissionDoesNotExist(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $server = $this->createActiveSyncServer(
            [
                'PHP_AUTH_USER' => 'alice',
                'PHP_AUTH_PW' => 'secret',
            ],
            [],
            Horde_ActiveSync::VERSION_FOURTEENONE
        );
        $driver = $this->createDriver();
        $this->setupGlobals(
            permsExists: false,
            globalVersion: Horde_ActiveSync::VERSION_FOURTEENONE
        );

        $driver->versionCallback($server);

        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_FOURTEENONE);
    }

    /**
     * No Basic-auth user, no User GET param, and no registry session: the
     * callback cannot resolve a principal and must not change the global
     * supported version even if permissions would allow an override.
     */
    public function testVersionCallbackSkipsWhenNoUsernameAvailable(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $server = $this->createActiveSyncServer(
            [],
            [],
            Horde_ActiveSync::VERSION_FOURTEENONE
        );
        // versionCallback() falls back to the driver's registry when neither
        // PHP_AUTH_USER nor the User GET parameter is set. Pin that call.
        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->expects($this->once())->method('getAuth')->willReturn(null);
        $driver = $this->createDriver(registry: $registry);
        $this->setupGlobals(
            permsVersion: Horde_ActiveSync::VERSION_SIXTEEN,
            globalVersion: Horde_ActiveSync::VERSION_FOURTEENONE
        );

        $driver->versionCallback($server);

        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_FOURTEENONE);
    }

    /**
     * When getPermissions() returns an array (e.g. membership in groups with
     * different horde:activesync:version values), the effective ceiling is the
     * lowest allowed version (14.1), not the highest (16.0), even when global
     * admin default is 16.0.
     */
    public function testVersionCallbackSelectsRestrictiveMinimumFromPermissionArray(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $server = $this->createActiveSyncServer(
            [
                'PHP_AUTH_USER' => 'alice',
                'PHP_AUTH_PW' => 'secret',
            ],
            [],
            Horde_ActiveSync::VERSION_SIXTEEN
        );
        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_SIXTEEN);

        $driver = $this->createDriver();
        $this->setupGlobals(
            permsVersion: [
                Horde_ActiveSync::VERSION_SIXTEEN,
                Horde_ActiveSync::VERSION_FOURTEENONE,
            ],
            expectedUsername: 'alice',
            globalVersion: Horde_ActiveSync::VERSION_SIXTEEN
        );

        $driver->versionCallback($server);

        $this->assertSupportedVersionsUpTo($server, Horde_ActiveSync::VERSION_FOURTEENONE);
    }

    /**
     * Build a minimal Horde_ActiveSync server for versionCallback() tests.
     *
     * @param array<string, string> $serverVars  $_SERVER values (e.g. PHP_AUTH_USER)
     * @param array<string, string> $getVars     GET parameters (e.g. User, DeviceId)
     * @param string|null $globalVersion        If set, apply setSupportedVersion()
     *                                          like Horde_Core_Factory_ActiveSyncServer
     */
    private function createActiveSyncServer(
        array $serverVars = [],
        array $getVars = [],
        ?string $globalVersion = null
    ): Horde_ActiveSync {
        // The Horde_ActiveSync constructor calls $driver->setProtocolVersion()
        // exactly once and stores driver+state for later. versionCallback()
        // never reads either of these via the server (it only uses
        // request/setSupportedVersion/getSupportedVersions).
        $mockDriver = $this->getMockSkipConstructor('Horde_ActiveSync_Driver_Base');
        $mockDriver->expects($this->once())->method('setProtocolVersion');
        $input = fopen('php://memory', 'wb+');
        $decoder = new Horde_ActiveSync_Wbxml_Decoder($input);
        $output = fopen('php://memory', 'wb+');
        $encoder = new Horde_ActiveSync_Wbxml_Encoder($output);
        $state = $this->expectUntouched('Horde_ActiveSync_State_Base');

        $request = new Horde_Controller_Request_Mock([
            'server' => $serverVars,
            'get' => $getVars,
        ]);

        $server = new Horde_ActiveSync($mockDriver, $decoder, $encoder, $state, $request);

        if ($globalVersion !== null) {
            $server->setSupportedVersion($globalVersion);
        }

        return $server;
    }

    private function assertSupportedVersionsUpTo(
        Horde_ActiveSync $server,
        string $version
    ): void {
        $this->assertSame(
            $this->supportedVersionsUpTo($version),
            $server->getSupportedVersions()
        );
    }

    private function supportedVersionsUpTo(string $maxVersion): string
    {
        $supported = [
            Horde_ActiveSync::VERSION_TWOFIVE,
            Horde_ActiveSync::VERSION_TWELVE,
            Horde_ActiveSync::VERSION_TWELVEONE,
            Horde_ActiveSync::VERSION_FOURTEEN,
            Horde_ActiveSync::VERSION_FOURTEENONE,
            Horde_ActiveSync::VERSION_SIXTEEN,
            Horde_ActiveSync::VERSION_SIXTEENONE,
        ];

        $index = array_search($maxVersion, $supported, true);
        if ($index === false) {
            throw new InvalidArgumentException('Unknown EAS version: ' . $maxVersion);
        }

        return implode(',', array_slice($supported, 0, $index + 1));
    }

    private function createDriver(?Horde_Registry $registry = null): Horde_Core_ActiveSync_Driver
    {
        return new Horde_Core_ActiveSync_Driver([
            'connector' => $this->expectUntouched(Horde_Core_ActiveSync_Connector::class),
            'auth' => $this->expectUntouched(Horde_Core_ActiveSync_Auth::class),
            'serverrequest' => new ServerRequest('POST', '/'),
            'registry' => $registry ?? $this->expectUntouched(Horde_Registry::class),
            'state' => $this->createDriverStateMock(),
        ]);
    }

    /**
     * State mock pinned to the calls Horde_ActiveSync_Driver_Base::__construct
     * makes during driver setup. versionCallback() itself never touches state.
     */
    private function createDriverStateMock(): MockObject
    {
        $state = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $state->expects($this->once())->method('setLogger');
        $state->expects($this->once())->method('setBackend');

        return $state;
    }

    /**
     * Build a mock for $className that expects to be untouched by both
     * driver construction and the method under test (versionCallback).
     * Pins isolation: future drift that adds calls to these collaborators
     * inside versionCallback() fails the test.
     *
     * @param class-string $className
     */
    private function expectUntouched(string $className): MockObject
    {
        $mock = $this->getMockSkipConstructor($className);
        $mock->expects($this->never())->method($this->anything());

        return $mock;
    }

    /**
     * Replace $GLOBALS conf/registry/injector with isolated test doubles.
     *
     * @param string|array<int, string>|null $permsVersion     Return value of
     *                                                         getPermissions()
     * @param string|null $expectedUsername                    Principal passed to
     *                                                         getPermissions()
     * @param bool $permsExists                                Whether
     *                                                         horde:activesync:version exists
     * @param string|null $globalVersion                      conf['activesync']['version']
     */
    private function setupGlobals(
        string|array|null $permsVersion = null,
        ?string $expectedUsername = null,
        bool $permsExists = true,
        ?string $globalVersion = null
    ): void {
        foreach (['injector', 'registry', 'conf'] as $key) {
            $this->savedGlobals[$key] = $GLOBALS[$key] ?? null;
        }

        $activesyncConf = [
            'autodiscovery' => 'full',
            'version_mode' => 'user',
        ];
        if ($globalVersion !== null) {
            $activesyncConf['version'] = $globalVersion;
        }

        $GLOBALS['conf'] = [
            'activesync' => $activesyncConf,
        ];

        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('getAuth')->willReturn('');
        $GLOBALS['registry'] = $registry;

        $perms = $this->getMockBuilder('Horde_Perms_Null')
            ->disableOriginalConstructor()
            ->onlyMethods(['exists', 'getPermissions'])
            ->getMock();

        if ($expectedUsername !== null || $permsExists === false) {
            // versionCallback resolved a username (or the test wants to assert
            // the missing-permission path); driver consults exists() once.
            $perms->expects($this->once())
                ->method('exists')
                ->with('horde:activesync:version')
                ->willReturn($permsExists);
        } else {
            // No username resolvable — driver short-circuits before asking
            // perms.
            $perms->expects($this->never())->method('exists');
        }

        if ($permsExists && $expectedUsername !== null) {
            $perms->expects($this->once())
                ->method('getPermissions')
                ->with('horde:activesync:version', $expectedUsername)
                ->willReturn($permsVersion);
        } else {
            $perms->expects($this->never())->method('getPermissions');
        }

        $injector = $this->createStub(Horde_Injector::class);
        $injector->method('getInstance')
            ->willReturnCallback(function (string $class) use ($perms) {
                if ($class === 'Horde_Perms') {
                    return $perms;
                }

                throw new Horde_Exception('Not configured: ' . $class);
            });
        $GLOBALS['injector'] = $injector;
    }
}
