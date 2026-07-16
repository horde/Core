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

use Horde\Core\Test\Support\MockSkipConstructorTrait;
use Horde\Http\ServerRequest;
use Horde_ActiveSync;
use Horde_Core_ActiveSync_Auth;
use Horde_Core_ActiveSync_Connector;
use Horde_Core_ActiveSync_Driver;
use Horde_Date;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Horde_Core_ActiveSync_Driver::resolveRecipient().
 */
#[CoversMethod(Horde_Core_ActiveSync_Driver::class, 'resolveRecipient')]
class DriverResolveRecipientTest extends TestCase
{
    use MockSkipConstructorTrait;

    public function testAvailabilityIgnoresFalseFreeBusyLookup(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $email = 'alice@example.com';
        $gal = 'gal';

        $connector = $this->getMockSkipConstructor(
            Horde_Core_ActiveSync_Connector::class,
            ['resolveRecipient', 'contacts_getGal']
        );
        $connector->expects($this->exactly(2))
            ->method('resolveRecipient')
            ->willReturnOnConsecutiveCalls(
                [
                    $email => [
                        [
                            'name' => 'Alice Example',
                            'email' => $email,
                            'source' => $gal,
                            'smimePublicKey' => null,
                        ],
                    ],
                ],
                false
            );
        $connector->expects($this->once())
            ->method('contacts_getGal')
            ->willReturn($gal);

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $connector,
            'auth' => $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class),
            'serverrequest' => new ServerRequest('POST', '/'),
            'registry' => $this->getMockSkipConstructor(Horde_Registry::class),
            'state' => $this->createDriverStateMock(),
            'imap' => null,
        ]);

        $results = $driver->resolveRecipient('availability', $email, [
            'maxambiguous' => 0,
            'starttime' => new Horde_Date('2026-07-15T09:00:00.000Z', 'UTC'),
            'endtime' => new Horde_Date('2026-07-15T17:00:00.000Z', 'UTC'),
        ]);

        $this->assertCount(1, $results);
        $this->assertSame($email, $results[0]['emailaddress']);
        $this->assertSame(Horde_ActiveSync::RESOLVE_RESULT_GAL, $results[0]['type']);
        $this->assertFalse($results[0]['availability']);
    }

    public function testAvailabilityUsesKeyedFalseFreeBusyResult(): void
    {
        if (!class_exists('Horde_ActiveSync_State_Sql')) {
            $this->markTestSkipped('horde/activesync not available');
        }

        $email = 'alice@example.com';
        $gal = 'gal';

        $connector = $this->getMockSkipConstructor(
            Horde_Core_ActiveSync_Connector::class,
            ['resolveRecipient', 'contacts_getGal']
        );
        $connector->expects($this->exactly(2))
            ->method('resolveRecipient')
            ->willReturnOnConsecutiveCalls(
                [
                    $email => [
                        [
                            'name' => 'Alice Example',
                            'email' => $email,
                            'source' => $gal,
                            'smimePublicKey' => null,
                        ],
                    ],
                ],
                [$email => false]
            );
        $connector->expects($this->once())
            ->method('contacts_getGal')
            ->willReturn($gal);

        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $connector,
            'auth' => $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class),
            'serverrequest' => new ServerRequest('POST', '/'),
            'registry' => $this->getMockSkipConstructor(Horde_Registry::class),
            'state' => $this->createDriverStateMock(),
            'imap' => null,
        ]);

        $results = $driver->resolveRecipient('availability', $email, [
            'maxambiguous' => 0,
            'starttime' => new Horde_Date('2026-07-15T09:00:00.000Z', 'UTC'),
            'endtime' => new Horde_Date('2026-07-15T17:00:00.000Z', 'UTC'),
        ]);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['availability']);
    }

    private function createDriverStateMock(): MockObject
    {
        $state = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $state->expects($this->once())->method('setLogger');
        $state->expects($this->once())->method('setBackend');

        return $state;
    }
}
