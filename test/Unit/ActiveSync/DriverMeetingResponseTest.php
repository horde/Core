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
use Horde_ActiveSync_Exception;
use Horde_ActiveSync_Request_MeetingResponse;
use Horde_Core_ActiveSync_Auth;
use Horde_Core_ActiveSync_Connector;
use Horde_Core_ActiveSync_Driver;
use Horde_Date;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

/**
 * Unit tests for Horde_Core_ActiveSync_Driver::meetingResponse().
 */
#[CoversMethod(Horde_Core_ActiveSync_Driver::class, 'meetingResponse')]
class DriverMeetingResponseTest extends TestCase
{
    use MockSkipConstructorTrait;

    private array $savedGlobals = [];

    protected function tearDown(): void
    {
        if (array_key_exists('injector', $this->savedGlobals)) {
            $GLOBALS['injector'] = $this->savedGlobals['injector'];
        } else {
            unset($GLOBALS['injector']);
        }

        parent::tearDown();
    }

    public function testMeetingResponseStoresAttendeeProposalWhenProposedTimesArePresent(): void
    {
        $this->requireMeetingResponseDependencies();
        $this->installIdentityInjector();

        $folderId = 'INBOX';
        $requestId = '42';
        $uid = 'server-event-uid';
        $expectedStart = new Horde_Date('2026-06-19T12:00:00.000Z', 'UTC');
        $expectedEnd = new Horde_Date('2026-06-19T13:00:00.000Z', 'UTC');

        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('call')
            ->with(
                'calendar/storeAttendeeProposal',
                $this->callback(static function (array $args) use ($uid, $expectedStart, $expectedEnd): bool {
                    return count($args) === 4
                        && $args[0] === $uid
                        && $args[1] === 'attendee@example.test'
                        && $args[2] instanceof Horde_Date
                        && $args[2]->timestamp === $expectedStart->timestamp
                        && $args[3] instanceof Horde_Date
                        && $args[3]->timestamp === $expectedEnd->timestamp;
                })
            );

        $imap = $this->getMockSkipConstructor('Horde_ActiveSync_Imap_Adapter');
        $imap->expects($this->once())
            ->method('getImapMessage')
            ->with($folderId, $requestId)
            ->willReturn($this->createImapMessage(
                $requestId,
                $this->buildVcalendar()
            ));
        $imap->expects($this->never())->method('deleteMessages');

        $connector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $connector->expects($this->once())
            ->method('calendar_import_vevent')
            ->willReturn($uid);

        $driver = $this->createDriver($registry, $imap, $connector);

        $this->expectException(Horde_ActiveSync_Exception::class);

        $driver->meetingResponse([
            'folderid' => $folderId,
            'requestid' => $requestId,
            'response' => Horde_ActiveSync_Request_MeetingResponse::RESPONSE_ACCEPTED,
            'proposedstarttime' => '2026-06-19T12:00:00.000Z',
            'proposedendtime' => '2026-06-19T13:00:00.000Z',
        ]);
    }

    public function testMeetingResponseSkipsAttendeeProposalWhenCounterProposalsAreDisallowed(): void
    {
        $this->requireMeetingResponseDependencies();
        $this->installIdentityInjector();

        $folderId = 'INBOX';
        $requestId = '42';
        $uid = 'server-event-uid';

        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->expects($this->never())->method('call');

        $imap = $this->getMockSkipConstructor('Horde_ActiveSync_Imap_Adapter');
        $imap->expects($this->once())
            ->method('getImapMessage')
            ->with($folderId, $requestId)
            ->willReturn($this->createImapMessage(
                $requestId,
                $this->buildVcalendar(['X-MS-DISALLOW-COUNTER:TRUE'])
            ));
        $imap->expects($this->once())
            ->method('deleteMessages')
            ->with([$requestId], $folderId);

        $connector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $connector->expects($this->once())
            ->method('calendar_import_vevent')
            ->willReturn($uid);

        $driver = $this->createDriver($registry, $imap, $connector);

        $this->assertSame($uid, $driver->meetingResponse([
            'folderid' => $folderId,
            'requestid' => $requestId,
            'response' => Horde_ActiveSync_Request_MeetingResponse::RESPONSE_ACCEPTED,
            'proposedstarttime' => '2026-06-19T12:00:00.000Z',
            'proposedendtime' => '2026-06-19T13:00:00.000Z',
        ]));
    }

    public function testMeetingResponseSkipsAttendeeProposalWhenProposedStartTimeIsInvalid(): void
    {
        $this->requireMeetingResponseDependencies();
        $this->installIdentityInjector();

        $folderId = 'INBOX';
        $requestId = '42';
        $uid = 'server-event-uid';

        $registry = $this->getMockSkipConstructor(Horde_Registry::class);
        $registry->expects($this->never())->method('call');

        $imap = $this->getMockSkipConstructor('Horde_ActiveSync_Imap_Adapter');
        $imap->expects($this->once())
            ->method('getImapMessage')
            ->with($folderId, $requestId)
            ->willReturn($this->createImapMessage(
                $requestId,
                $this->buildVcalendar()
            ));
        $imap->expects($this->once())
            ->method('deleteMessages')
            ->with([$requestId], $folderId);

        $connector = $this->getMockSkipConstructor(Horde_Core_ActiveSync_Connector::class);
        $connector->expects($this->once())
            ->method('calendar_import_vevent')
            ->willReturn($uid);

        $driver = $this->createDriver($registry, $imap, $connector);

        $this->assertSame($uid, $driver->meetingResponse([
            'folderid' => $folderId,
            'requestid' => $requestId,
            'response' => Horde_ActiveSync_Request_MeetingResponse::RESPONSE_ACCEPTED,
            'proposedstarttime' => 'not-a-date',
            'proposedendtime' => '2026-06-19T13:00:00.000Z',
        ]));
    }

    private function requireMeetingResponseDependencies(): void
    {
        foreach ([
            'Horde_ActiveSync_State_Sql',
            'Horde_ActiveSync_Imap_Adapter',
            'Horde_ActiveSync_Request_MeetingResponse',
            'Horde_Icalendar',
            'Horde_Date',
        ] as $className) {
            if (!class_exists($className)) {
                $this->markTestSkipped(sprintf('%s not available', $className));
            }
        }
    }

    private function createDriver(
        Horde_Registry $registry,
        object $imap,
        Horde_Core_ActiveSync_Connector $connector,
    ): Horde_Core_ActiveSync_Driver {
        $driver = new Horde_Core_ActiveSync_Driver([
            'connector' => $connector,
            'auth' => $this->getMockSkipConstructor(Horde_Core_ActiveSync_Auth::class),
            'serverrequest' => new ServerRequest('POST', '/'),
            'registry' => $registry,
            'state' => $this->createDriverStateMock(),
            'imap' => $imap,
        ]);

        $this->setProperty($driver, '_user', 'attendee');
        $this->setProperty($driver, '_version', Horde_ActiveSync::VERSION_SIXTEENONE);
        $this->setProperty($driver, '_logger', new class {
            public function err(string $message, ?string $priority = null): void {}

            public function meta(string $message): void {}

            public function notice(string $message): void {}

            public function warn(string $message): void {}
        });

        return $driver;
    }

    private function createDriverStateMock(): MockObject
    {
        $state = $this->getMockSkipConstructor('Horde_ActiveSync_State_Sql');
        $state->expects($this->once())->method('setLogger');
        $state->expects($this->once())->method('setBackend');

        return $state;
    }

    private function installIdentityInjector(string $email = 'attendee@example.test'): void
    {
        if (array_key_exists('injector', $GLOBALS)) {
            $this->savedGlobals['injector'] = $GLOBALS['injector'];
        }

        $identity = new class ($email) {
            public function __construct(private readonly string $email) {}

            public function getValue(string $key): ?string
            {
                return $key === 'from_addr' ? $this->email : null;
            }

            public function getFromAddress(): string
            {
                return $this->email;
            }
        };

        $identityFactory = new class ($identity) {
            public function __construct(private readonly object $identity) {}

            public function create(?string $user = null): object
            {
                return $this->identity;
            }
        };

        $GLOBALS['injector'] = new class ($identityFactory) {
            public function __construct(private readonly object $identityFactory) {}

            public function getInstance(string $class): object
            {
                if ($class === 'Horde_Core_Factory_Identity') {
                    return $this->identityFactory;
                }

                throw new RuntimeException(sprintf('Unexpected injector lookup: %s', $class));
            }
        };
    }

    /**
     * @return array<string, object>
     */
    private function createImapMessage(string $requestId, string $ical): array
    {
        $part = new class ($ical) {
            public function __construct(private readonly string $ical) {}

            public function getContents(): string
            {
                return $this->ical;
            }

            public function getCharset(): string
            {
                return 'UTF-8';
            }
        };

        $message = new class ($part) {
            public function __construct(private readonly object $part) {}

            public function hasiCalendar(): object
            {
                return $this->part;
            }
        };

        return [$requestId => $message];
    }

    /**
     * @param list<string> $extraEventLines
     */
    private function buildVcalendar(array $extraEventLines = []): string
    {
        return implode("\r\n", array_merge([
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Horde//Core Test//EN',
            'BEGIN:VEVENT',
            'UID:test-event-uid',
            'DTSTART:20260619T100000Z',
            'DTEND:20260619T110000Z',
            'ATTENDEE:mailto:attendee@example.test',
        ], $extraEventLines, [
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]));
    }

    private function setProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }
}
