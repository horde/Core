<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package Core
 * @subpackage UnitTests
 */

namespace Horde\Core\Test\Unit\ActiveSync;

use Horde\Http\ServerRequest;
use Horde_ActiveSync;
use Horde_Core_ActiveSync_Auth;
use Horde_Core_ActiveSync_Connector;
use Horde_Core_ActiveSync_Driver;
use Horde_Exception;
use Horde_Log_Handler_Null;
use Horde_Log_Logger;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

/**
 * Regression: calendar instance deletes must not foreach() a string UID when
 * calendar_delete() throws (PHP 8 TypeError / warning on recovery path).
 */
#[CoversMethod(Horde_Core_ActiveSync_Driver::class, 'deleteMessage')]
class DriverDeleteMessageInstanceTest extends TestCase
{
    private function createDriver(Horde_Core_ActiveSync_Connector $connector): Horde_Core_ActiveSync_Driver
    {
        $state = $this->getMockBuilder('Horde_ActiveSync_State_Sql')
            ->disableOriginalConstructor()
            ->getMock();
        $state->method('setLogger');
        $state->method('setBackend');

        $auth = $this->getMockBuilder(Horde_Core_ActiveSync_Auth::class)
            ->disableOriginalConstructor()
            ->getMock();
        $registry = $this->getMockBuilder(Horde_Registry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $connector,
            'auth' => $auth,
            'serverrequest' => new ServerRequest('POST', '/'),
            'registry' => $registry,
            'state' => $state,
        ]);
        $driver->setLogger(new Horde_Log_Logger(new Horde_Log_Handler_Null()));

        return $driver;
    }

    public function testCalendarInstanceDeleteErrorPathDoesNotForeachString(): void
    {
        $uid = 'event-uid-example';
        $instanceId = '20250808T153000Z';
        $folder = 'Calendar:cal-example';

        $connector = $this->getMockBuilder(Horde_Core_ActiveSync_Connector::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['calendar_delete', 'calendar_getActionTimestamp'])
            ->getMock();
        $connector->expects($this->once())
            ->method('calendar_delete')
            ->with($uid, 'cal-example', $instanceId)
            ->willThrowException(new Horde_Exception('not found'));
        $connector->expects($this->once())
            ->method('calendar_getActionTimestamp')
            ->with($uid, 'delete', 'cal-example')
            ->willReturn(false);

        $driver = $this->createDriver($connector);
        $results = $driver->deleteMessage($folder, [$uid => $instanceId], true);

        $this->assertSame([], $results);
    }

    public function testCalendarInstanceDeleteErrorPathReportsSuccessfulUid(): void
    {
        $uid = 'event-uid-example';
        $instanceId = '20250808T153000Z';
        $folder = 'Calendar:cal-example';

        $connector = $this->getMockBuilder(Horde_Core_ActiveSync_Connector::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['calendar_delete', 'calendar_getActionTimestamp'])
            ->getMock();
        $connector->method('calendar_delete')
            ->willThrowException(new Horde_Exception('not found'));
        $connector->method('calendar_getActionTimestamp')
            ->willReturn(1234567890);

        $driver = $this->createDriver($connector);
        $results = $driver->deleteMessage($folder, [$uid => $instanceId], true);

        $this->assertSame([$uid], $results);
    }
}
