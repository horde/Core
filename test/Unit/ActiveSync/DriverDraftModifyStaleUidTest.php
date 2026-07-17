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
use Horde_ActiveSync_Device;
use Horde_ActiveSync_Imap_Adapter;
use Horde_ActiveSync_Message_AirSyncBaseBody;
use Horde_ActiveSync_Message_Mail;
use Horde_Core_ActiveSync_Auth;
use Horde_Core_ActiveSync_Connector;
use Horde_Core_ActiveSync_Driver;
use Horde_Log_Handler_Null;
use Horde_Log_Logger;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

/**
 * Defense-in-depth for Issue #85: Draft Modify must not append-as-new when
 * the client ServerId is missing/stale on IMAP.
 */
#[CoversMethod(Horde_Core_ActiveSync_Driver::class, 'changeMessage')]
class DriverDraftModifyStaleUidTest extends TestCase
{
    public function testDraftModifyMissingUidDoesNotAppend(): void
    {
        if (!class_exists('Horde_ActiveSync_Message_Mail')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $folder = 'INBOX/Drafts';
        $staleUid = 100;

        $imap = $this->getMockBuilder(Horde_ActiveSync_Imap_Adapter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getImapMessage', 'appendMessage', 'deleteMessages', 'getSpecialMailboxes', 'setLogger'])
            ->getMock();
        $imap->method('getSpecialMailboxes')->willReturn([
            Horde_Core_ActiveSync_Driver::SPECIAL_DRAFTS => (object) ['value' => $folder],
        ]);
        $imap->expects($this->once())
            ->method('getImapMessage')
            ->with($folder, $staleUid)
            ->willReturn([]);
        $imap->expects($this->never())->method('appendMessage');
        $imap->expects($this->never())->method('deleteMessages');

        $state = $this->getMockBuilder('Horde_ActiveSync_State_Sql')
            ->disableOriginalConstructor()
            ->getMock();
        $state->method('setLogger');
        $state->method('setBackend');

        $connector = $this->getMockBuilder(Horde_Core_ActiveSync_Connector::class)
            ->disableOriginalConstructor()
            ->getMock();
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
            'imap' => $imap,
        ]);
        $driver->setLogger(new Horde_Log_Logger(new Horde_Log_Handler_Null()));
        $driver->setProtocolVersion(Horde_ActiveSync::VERSION_SIXTEEN);

        $userProp = new \ReflectionProperty(
            \Horde_ActiveSync_Driver_Base::class,
            '_user'
        );
        $userProp->setAccessible(true);
        $userProp->setValue($driver, 'alice@example.com');

        $message = new Horde_ActiveSync_Message_Mail([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);
        $message->to = 'alice@example.com';
        $message->subject = 'Draft subject';
        $body = new Horde_ActiveSync_Message_AirSyncBaseBody([
            'protocolversion' => Horde_ActiveSync::VERSION_SIXTEEN,
        ]);
        $body->type = Horde_ActiveSync::BODYPREF_TYPE_PLAIN;
        $body->data = 'body';
        $message->airsyncbasebody = $body;

        $device = $this->createMock(Horde_ActiveSync_Device::class);

        $result = $driver->changeMessage($folder, $staleUid, $message, $device);

        $this->assertFalse($result);
    }
}
