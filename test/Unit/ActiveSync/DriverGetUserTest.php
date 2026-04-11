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

use Horde\Test\TestCase;
use Horde\Http\ServerRequest;
use Horde\Http\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde_Core_ActiveSync_Driver;
use Horde_Core_ActiveSync_Auth;
use Horde_Core_ActiveSync_Connector;
use Horde_Registry;
use InvalidArgumentException;

/**
 * Unit tests for Horde_Core_ActiveSync_Driver::getUser() priority logic
 *
 * Note: These tests require horde/activesync to be available.
 * Run with: phpunit --bootstrap test/bootstrap.php test/Unit/ActiveSync/DriverGetUserTest.php
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 * @subpackage UnitTests
 */
#[CoversClass(Horde_Core_ActiveSync_Driver::class)]
class DriverGetUserTest extends TestCase
{
    /**
     * Test Priority 1: Authenticated user takes precedence
     */
    public function testGetUserReturnsAuthenticatedUser(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $serverRequest = new ServerRequest('POST', '/');

        $mockConnector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $mockAuth = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class);
        $mockAuth->method('authenticate')->willReturn(true);
        $mockState = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $mockRegistry = $this->getMockSkipConstructor(Horde_Registry::class);

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $mockConnector,
            'auth' => $mockAuth,
            'serverrequest' => $serverRequest,
            'registry' => $mockRegistry,
            'state' => $mockState,
        ]);

        // Simulate authentication - this sets _authUser in parent
        $result = $driver->authenticate('authenticated_user', 'password');

        $this->assertEquals('authenticated_user', $driver->getUser());
    }

    /**
     * Test Priority 2: GET parameter when no authenticated user
     */
    public function testGetUserFallsBackToGetParameter(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $uri = new Uri('http://example.com/path?User=get_param_user&DeviceId=123');
        $serverRequest = new ServerRequest('POST', $uri);

        $mockConnector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $mockAuth = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class);
        $mockState = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $mockRegistry = $this->getMockSkipConstructor(Horde_Registry::class);

        $mockRegistry->expects($this->never())
            ->method('getAuth');

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $mockConnector,
            'auth' => $mockAuth,
            'serverrequest' => $serverRequest,
            'registry' => $mockRegistry,
            'state' => $mockState,
        ]);

        // No authentication, should use GET parameter
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

        $serverRequest = new ServerRequest('POST', '/');

        $mockConnector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $mockAuth = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class);
        $mockState = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $mockRegistry = $this->getMockSkipConstructor(Horde_Registry::class);

        $mockRegistry->expects($this->once())
            ->method('getAuth')
            ->willReturn('registry_user');

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $mockConnector,
            'auth' => $mockAuth,
            'serverrequest' => $serverRequest,
            'registry' => $mockRegistry,
            'state' => $mockState,
        ]);

        // No authentication, no GET parameter, should use registry
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

        $uri = new Uri('http://example.com/path?User=get_param_user');
        $serverRequest = new ServerRequest('POST', $uri);

        $mockConnector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $mockAuth = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class);
        $mockAuth->method('authenticate')->willReturn(true);
        $mockState = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $mockRegistry = $this->getMockSkipConstructor(Horde_Registry::class);

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $mockConnector,
            'auth' => $mockAuth,
            'serverrequest' => $serverRequest,
            'registry' => $mockRegistry,
            'state' => $mockState,
        ]);

        // Authenticate, should ignore GET parameter
        $driver->authenticate('authenticated_user', 'password');

        $this->assertEquals('authenticated_user', $driver->getUser());
    }

    /**
     * Test: GET parameter overrides registry
     */
    public function testGetParameterOverridesRegistry(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $uri = new Uri('http://example.com/path?User=get_param_user');
        $serverRequest = new ServerRequest('POST', $uri);

        $mockConnector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $mockAuth = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class);
        $mockState = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $mockRegistry = $this->getMockSkipConstructor(Horde_Registry::class);

        $mockRegistry->expects($this->never())
            ->method('getAuth');

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $mockConnector,
            'auth' => $mockAuth,
            'serverrequest' => $serverRequest,
            'registry' => $mockRegistry,
            'state' => $mockState,
        ]);

        // No authentication, GET parameter present, should not call registry
        $this->assertEquals('get_param_user', $driver->getUser());
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

        $mockConnector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $mockAuth = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class);
        $mockState = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $mockRegistry = $this->getMockSkipConstructor(Horde_Registry::class);

        new Horde_Core_ActiveSync_Driver([
            'connector' => $mockConnector,
            'auth' => $mockAuth,
            'registry' => $mockRegistry,
            'state' => $mockState,
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

        $serverRequest = new ServerRequest('POST', '/');

        $mockConnector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $mockAuth = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class);
        $mockState = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');

        new Horde_Core_ActiveSync_Driver([
            'connector' => $mockConnector,
            'auth' => $mockAuth,
            'serverrequest' => $serverRequest,
            'state' => $mockState,
            // Missing registry
        ]);
    }
}
