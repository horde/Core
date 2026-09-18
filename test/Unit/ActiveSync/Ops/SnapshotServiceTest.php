<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Unit\ActiveSync\Ops;

use Horde\ActiveSync\Ops\DeviceHealth;
use Horde\ActiveSync\Ops\HealthEvaluator;
use Horde\ActiveSync\Ops\HealthStatus;
use Horde\Core\ActiveSync\Ops\DeviceLogPathResolver;
use Horde\Core\ActiveSync\Ops\SnapshotCriteria;
use Horde\Core\ActiveSync\Ops\SnapshotService;
use Horde_ActiveSync_State_Sql;
use Horde_Exception;
use Horde_Exception_NotFound;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Horde\Core\ActiveSync\Ops\SnapshotService
 * @covers \Horde\Core\ActiveSync\Ops\FleetSnapshot
 */
final class SnapshotServiceTest extends TestCase
{
    private const NOW = 1700000000;

    protected function setUp(): void
    {
        if (!class_exists(HealthEvaluator::class)) {
            $this->markTestSkipped('horde/activesync Ops support not available');
        }
    }

    public function testFleetBuildsExpectedSummaryAndLogPaths(): void
    {
        $snapshot = $this->service($this->state())->fleet(
            new SnapshotCriteria(limit: 0)
        );

        self::assertSame(3, $snapshot->summary->devices);
        self::assertSame(1, $snapshot->summary->critical);
        self::assertSame(1, $snapshot->summary->warn);
        self::assertSame(2, $snapshot->summary->stuck);
        self::assertSame(1, $snapshot->summary->blocked);
        self::assertSame(
            '/var/log/activesync/DEVICE1.txt',
            $this->byId($snapshot->devices, 'DEVICE1')->logPath
        );
    }

    public function testStuckOnlyFilter(): void
    {
        $snapshot = $this->service($this->state())->fleet(
            new SnapshotCriteria(stuckOnly: true, limit: 0)
        );

        self::assertSame(2, $snapshot->summary->devices);
        self::assertCount(2, $snapshot->devices);
        self::assertContainsOnlyInstancesOf(
            DeviceHealth::class,
            $snapshot->devices
        );
        foreach ($snapshot->devices as $device) {
            self::assertTrue($device->isStuck());
        }
    }

    public function testCriticalHealthFilter(): void
    {
        $snapshot = $this->service($this->state())->fleet(
            new SnapshotCriteria(
                healthMin: HealthStatus::CRITICAL,
                limit: 0
            )
        );

        self::assertSame(1, $snapshot->summary->devices);
        self::assertCount(1, $snapshot->devices);
    }

    public function testLimitDoesNotTruncateSummary(): void
    {
        $snapshot = $this->service($this->state())->fleet(
            new SnapshotCriteria(limit: 1)
        );

        self::assertSame(3, $snapshot->summary->devices);
        self::assertCount(1, $snapshot->devices);
    }

    public function testSortByUserUsesNaturalCaseInsensitiveOrder(): void
    {
        $snapshot = $this->service($this->state())->fleet(
            new SnapshotCriteria(
                limit: 0,
                sort: SnapshotCriteria::SORT_USER
            )
        );

        self::assertSame(
            ['DEVICE2', 'DEVICE3', 'DEVICE1'],
            array_column($snapshot->toArray()['devices'], 'deviceId')
        );
    }

    public function testDeviceUsesExactMatchAndLastSyncTimestamp(): void
    {
        $state = $this->createMock(Horde_ActiveSync_State_Sql::class);
        $rows = [
            $this->row('alice@example.com', 'DEVICE1'),
            $this->row('alice@example.com', 'DEVICE10'),
        ];
        $state->expects(self::once())
            ->method('listDevices')
            ->with('alice@example.com', ['device_id' => 'DEVICE1'])
            ->willReturn($rows);
        $state->expects(self::once())
            ->method('getSyncCache')
            ->with('DEVICE1', 'alice@example.com')
            ->willReturn($this->cache('DEVICE1'));
        $state->expects(self::once())
            ->method('getLastSyncTimestamp')
            ->with('DEVICE1', 'alice@example.com')
            ->willReturn(self::NOW - 5);

        $device = $this->service($state)->device(
            'alice@example.com',
            'DEVICE1'
        );

        self::assertSame('DEVICE1', $device->deviceId);
        self::assertSame(5, $device->ageSeconds);
    }

    public function testDeviceThrowsWhenNoExactMatchExists(): void
    {
        $state = $this->createMock(Horde_ActiveSync_State_Sql::class);
        $state->method('listDevices')->willReturn([
            $this->row('alice@example.com', 'DEVICE10'),
            $this->row('other@example.com', 'DEVICE1'),
        ]);

        $this->expectException(Horde_Exception_NotFound::class);

        $this->service($state)->device('alice@example.com', 'DEVICE1');
    }

    public function testSyncCacheFailureSkipsOnlyAffectedRow(): void
    {
        $state = $this->state('DEVICE2');

        $snapshot = $this->service($state)->fleet(
            new SnapshotCriteria(limit: 0)
        );

        self::assertSame(2, $snapshot->summary->devices);
        self::assertCount(2, $snapshot->devices);
        self::assertSame(
            ['DEVICE1', 'DEVICE3'],
            array_map(
                static fn ($device): string => $device->deviceId,
                $snapshot->devices
            )
        );
    }

    private function service(
        Horde_ActiveSync_State_Sql $state
    ): SnapshotService {
        return new SnapshotService(
            state: $state,
            logPaths: new DeviceLogPathResolver(
                'perdevice',
                '/var/log/activesync/'
            ),
            clock: static fn (): int => self::NOW
        );
    }

    private function state(
        ?string $throwFor = null
    ): Horde_ActiveSync_State_Sql&MockObject {
        $state = $this->createMock(Horde_ActiveSync_State_Sql::class);
        $state->method('listDevices')->willReturn($this->rows());
        $state->method('getSyncCache')->willReturnCallback(
            function (string $deviceId) use ($throwFor): array {
                if ($deviceId === $throwFor) {
                    throw new Horde_Exception('Unreadable SyncCache');
                }

                return $this->cache($deviceId);
            }
        );

        return $state;
    }

    private function rows(): array
    {
        return [
            $this->row('bob@example.com', 'DEVICE1'),
            $this->row(
                'alice@example.com',
                'DEVICE2',
                serialize(['blocked' => true])
            ),
            $this->row('alice@example.com', 'DEVICE3'),
        ];
    }

    private function row(
        string $user,
        string $deviceId,
        array|string $properties = []
    ): array {
        return [
            'device_id' => $deviceId,
            'device_type' => 'Phone',
            'device_user' => $user,
            'device_rwstatus' => 0,
            'device_accountonly_rwstatus' => 0,
            'device_properties' => $properties,
        ];
    }

    private function cache(string $deviceId): array
    {
        return match ($deviceId) {
            'DEVICE1' => [
                'timestamp' => self::NOW - 10,
                'hbinterval' => 600,
                'lasthbsyncstarted' => 0,
                'lastsyncendnormal' => 0,
                'foldersyncrequired' => 0,
                'collections' => [],
            ],
            'DEVICE2' => [
                'timestamp' => self::NOW - 100,
                'hbinterval' => 600,
                // 1000s > stuck threshold (600 + 60) but <= abandoned
                // threshold (2 * 660) -> hb_stuck, not hb_abandoned.
                'lasthbsyncstarted' => self::NOW - 1000,
                'lastsyncendnormal' => self::NOW - 2000,
                'foldersyncrequired' => 0,
                'collections' => [],
            ],
            default => [
                'timestamp' => self::NOW - 200,
                'hbinterval' => 600,
                'lasthbsyncstarted' => 0,
                'lastsyncendnormal' => 0,
                'foldersyncrequired' => 5,
                'collections' => [],
            ],
        };
    }

    private function byId(array $devices, string $deviceId): DeviceHealth
    {
        foreach ($devices as $device) {
            if ($device->deviceId === $deviceId) {
                return $device;
            }
        }

        self::fail(sprintf('Device %s not found.', $deviceId));
    }
}
