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

namespace Horde\Core\Test\Unit\ActiveSync;

use Horde\Core\Test\Support\MockSkipConstructorTrait;
use Horde\Http\ServerRequest;
use Horde_Core_ActiveSync_Auth;
use Horde_Core_ActiveSync_Connector;
use Horde_Core_ActiveSync_Driver;
use Horde_Registry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Unit tests for Horde_Core_ActiveSync_Driver::getUser() priority logic.
 *
 * The driver constructor stores connector/auth/registry/state collaborators.
 * getUser() touches at most the registry's getAuth(); the other three are
 * placeholders for the path under test. State has its setLogger/setBackend
 * called by the parent Driver_Base constructor — pin those.
 */
#[CoversClass(Horde_Core_ActiveSync_Driver::class)]
class DriverGetUserTest extends TestCase
{
    use MockSkipConstructorTrait;

    /**
     * Build a mock that asserts the path under test never invokes a method
     * on it. Future drift adding calls to a constructor-placeholder mock
     * fails the test, which keeps the contract pinned.
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
     * State mock pinned to the calls Horde_ActiveSync_Driver_Base::__construct
     * makes during driver setup.
     */
    private function createDriverStateMock(): MockObject
    {
        $state = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $state->expects($this->once())->method('setLogger');
        $state->expects($this->once())->method('setBackend');

        return $state;
    }

    /**
     * Test Priority 1: Authenticated user takes precedence
     */
    public function testGetUserReturnsAuthenticatedUser(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $this->expectUntouched(Horde_Core_ActiveSync_Connector::class),
            'auth' => $this->expectUntouched(Horde_Core_ActiveSync_Auth::class),
            'serverrequest' => new ServerRequest('POST', '/'),
            'registry' => $this->expectUntouched(Horde_Registry::class),
            'state' => $this->createDriverStateMock(),
        ]);

        // Set _authUser via reflection (authenticate() requires global $injector/$conf)
        $ref = new ReflectionProperty($driver, '_authUser');
        $ref->setValue($driver, 'authenticated_user');

        $this->assertEquals('authenticated_user', $driver->getUser());
    }

    /**
     * Test Priority 3: GET parameter is used only when neither an
     * authenticated user nor a registry-authenticated user is present.
     *
     * The registry (priority 2) is consulted before the client-supplied
     * ?User= value (priority 3); when it returns empty we fall back to the
     * GET parameter.
     */
    public function testGetUserFallsBackToGetParameter(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $serverRequest = (new ServerRequest('POST', '/'))
            ->withQueryParams(['User' => 'get_param_user', 'DeviceId' => '123']);

        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('getAuth')
            ->willReturn('');

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $this->expectUntouched(Horde_Core_ActiveSync_Connector::class),
            'auth' => $this->expectUntouched(Horde_Core_ActiveSync_Auth::class),
            'serverrequest' => $serverRequest,
            'registry' => $registry,
            'state' => $this->createDriverStateMock(),
        ]);

        // No authenticated user and empty registry auth: use GET parameter.
        $this->assertEquals('get_param_user', $driver->getUser());
    }

    /**
     * Test Priority 3: Registry fallback when no auth and no GET parameter
     */
    public function testGetUserFallsBackToRegistry(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        // Path 3 actively calls the registry — pin the call shape.
        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('getAuth')
            ->willReturn('registry_user');

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $this->expectUntouched(Horde_Core_ActiveSync_Connector::class),
            'auth' => $this->expectUntouched(Horde_Core_ActiveSync_Auth::class),
            'serverrequest' => new ServerRequest('POST', '/'),
            'registry' => $registry,
            'state' => $this->createDriverStateMock(),
        ]);

        $this->assertEquals('registry_user', $driver->getUser());
    }

    /**
     * Test: Authenticated user overrides GET parameter
     */
    public function testAuthenticatedUserOverridesGetParameter(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $serverRequest = (new ServerRequest('POST', '/'))
            ->withQueryParams(['User' => 'get_param_user']);

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $this->expectUntouched(Horde_Core_ActiveSync_Connector::class),
            'auth' => $this->expectUntouched(Horde_Core_ActiveSync_Auth::class),
            'serverrequest' => $serverRequest,
            'registry' => $this->expectUntouched(Horde_Registry::class),
            'state' => $this->createDriverStateMock(),
        ]);

        $ref = new ReflectionProperty($driver, '_authUser');
        $ref->setValue($driver, 'authenticated_user');

        $this->assertEquals('authenticated_user', $driver->getUser());
    }

    /**
     * Test: a registry-authenticated user overrides the client-supplied
     * ?User= GET parameter.
     *
     * An authenticated identity MUST take precedence over any client-controlled
     * value. The ?User= parameter is not proof of identity and is only used as
     * a last resort when no authenticated user (auth flow or registry) exists,
     * so a non-empty registry auth wins here.
     */
    public function testRegistryOverridesGetParameter(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $serverRequest = (new ServerRequest('POST', '/'))
            ->withQueryParams(['User' => 'get_param_user']);

        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('getAuth')
            ->willReturn('registry_user');

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $this->expectUntouched(Horde_Core_ActiveSync_Connector::class),
            'auth' => $this->expectUntouched(Horde_Core_ActiveSync_Auth::class),
            'serverrequest' => $serverRequest,
            'registry' => $registry,
            'state' => $this->createDriverStateMock(),
        ]);

        $this->assertEquals('registry_user', $driver->getUser());
    }

    /**
     * Test: Constructor requires serverrequest
     */
    public function testConstructorRequiresServerRequest(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required PSR-7 ServerRequest object.');

        // Constructor fails before parent::__construct runs, so state is
        // not pinned with setLogger/setBackend here.
        new Horde_Core_ActiveSync_Driver([
            'connector' => $this->expectUntouched(Horde_Core_ActiveSync_Connector::class),
            'auth' => $this->expectUntouched(Horde_Core_ActiveSync_Auth::class),
            'registry' => $this->expectUntouched(Horde_Registry::class),
            'state' => $this->expectUntouched('Horde_ActiveSync_State_Sql'),
            // Missing serverrequest
        ]);
    }

    /**
     * Test: Constructor requires registry
     */
    public function testConstructorRequiresRegistry(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required Horde_Registry object.');

        new Horde_Core_ActiveSync_Driver([
            'connector' => $this->expectUntouched(Horde_Core_ActiveSync_Connector::class),
            'auth' => $this->expectUntouched(Horde_Core_ActiveSync_Auth::class),
            'serverrequest' => new ServerRequest('POST', '/'),
            'state' => $this->expectUntouched('Horde_ActiveSync_State_Sql'),
            // Missing registry
        ]);
    }
}
