<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 * @subpackage UnitTests
 */

namespace Horde\Core\Test\Unit\ActiveSync;

use Horde\Core\Test\Support\MockSkipConstructorTrait;
use Horde\Exception\InvalidDataThrowable;
use Horde\Http\ServerRequest;
use Horde_ActiveSync_Message_Contact;
use Horde_ActiveSync_Request_Sync;
use Horde_Core_ActiveSync_Auth;
use Horde_Core_ActiveSync_Connector;
use Horde_Core_ActiveSync_Driver;
use Horde_Exception;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the failure result of
 * Horde_Core_ActiveSync_Driver::changeMessage().
 *
 * A PIM backend that rejects the client's data (InvalidDataThrowable, e.g.
 * Turba refusing an attribute over its configured length) must be reported
 * as STATUS_INVALID so the client stops resending the item; any other
 * backend failure stays a retryable false.
 */
#[CoversClass(Horde_Core_ActiveSync_Driver::class)]
class DriverChangeMessageStatusTest extends TestCase
{
    use MockSkipConstructorTrait;

    public function testRejectedClientDataIsReportedAsStatusInvalid(): void
    {
        $connector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $connector->expects($this->once())
            ->method('contacts_replace')
            ->willThrowException($this->invalidDataException());

        $result = $this->driver($connector)->changeMessage(
            Horde_Core_ActiveSync_Driver::CONTACTS_FOLDER_UID,
            'contact-1',
            new Horde_ActiveSync_Message_Contact(),
            null
        );

        $this->assertSame(
            ['error' => Horde_ActiveSync_Request_Sync::STATUS_INVALID],
            $result
        );
    }

    public function testRejectedClientDataOnAddIsReportedAsStatusInvalid(): void
    {
        $connector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $connector->expects($this->once())
            ->method('contacts_import')
            ->willThrowException($this->invalidDataException());

        $result = $this->driver($connector)->changeMessage(
            Horde_Core_ActiveSync_Driver::CONTACTS_FOLDER_UID,
            null,
            new Horde_ActiveSync_Message_Contact(),
            null
        );

        $this->assertSame(
            ['error' => Horde_ActiveSync_Request_Sync::STATUS_INVALID],
            $result
        );
    }

    public function testOtherBackendFailureStaysRetryableFalse(): void
    {
        $connector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $connector->expects($this->once())
            ->method('contacts_replace')
            ->willThrowException(new Horde_Exception('Server error when saving data.'));

        $result = $this->driver($connector)->changeMessage(
            Horde_Core_ActiveSync_Driver::CONTACTS_FOLDER_UID,
            'contact-1',
            new Horde_ActiveSync_Message_Contact(),
            null
        );

        $this->assertFalse($result);
    }

    private function invalidDataException(): Horde_Exception
    {
        return new class ('Home Postal Code: Value is over the maximum length of 10.') extends Horde_Exception implements InvalidDataThrowable {
        };
    }

    private function driver(Horde_Core_ActiveSync_Connector $connector): Horde_Core_ActiveSync_Driver
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $state = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $auth = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class);
        $auth->expects($this->never())->method($this->anything());
        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->expects($this->never())->method($this->anything());

        return new Horde_Core_ActiveSync_Driver([
            'connector' => $connector,
            'auth' => $auth,
            'serverrequest' => new ServerRequest('POST', '/'),
            'registry' => $registry,
            'state' => $state,
        ]);
    }
}
