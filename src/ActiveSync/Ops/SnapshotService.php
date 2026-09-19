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

namespace Horde\Core\ActiveSync\Ops;

use Closure;
use Horde\ActiveSync\Ops\DeviceHealth;
use Horde\ActiveSync\Ops\DeviceHealthFactory;
use Horde\ActiveSync\Ops\FleetSummary;
use Horde\ActiveSync\Ops\HealthEvaluator;
use Horde\ActiveSync\Ops\HealthOptions;
use Horde\ActiveSync\Ops\HealthStatus;
use Horde_ActiveSync_State_Base;
use Horde_Exception;
use Horde_Exception_NotFound;
use Horde_Log_Logger;

final class SnapshotService
{
    private readonly Closure $factoryBuilder;
    private readonly Closure $clock;

    public function __construct(
        private readonly Horde_ActiveSync_State_Base $state,
        private readonly DeviceLogPathResolver $logPaths,
        private readonly ?Horde_Log_Logger $logger = null,
        ?callable $factoryBuilder = null,
        ?callable $clock = null
    ) {
        $this->factoryBuilder = Closure::fromCallable(
            $factoryBuilder
                ?? static fn (HealthOptions $options): DeviceHealthFactory =>
                    new DeviceHealthFactory(new HealthEvaluator($options))
        );
        $this->clock = Closure::fromCallable($clock ?? time(...));
    }

    public function summary(SnapshotCriteria $criteria): FleetSummary
    {
        return $this->fleet($criteria)->summary;
    }

    public function fleet(SnapshotCriteria $criteria): FleetSnapshot
    {
        $now = ($this->clock)();
        $factory = ($this->factoryBuilder)($criteria->toHealthOptions($now));
        $filter = $criteria->deviceId === null
            ? []
            : ['device_id' => $criteria->deviceId];
        $devices = [];

        foreach ($this->state->listDevices($criteria->user, $filter) as $row) {
            $deviceId = (string) ($row['device_id'] ?? '');
            $user = (string) ($row['device_user'] ?? '');

            try {
                $cache = $this->state->getSyncCache($deviceId, $user);
            } catch (Horde_Exception $e) {
                $this->logger?->warn($e);
                continue;
            }

            // SyncCache timestamp is the fleet activity proxy. Loading the
            // separate last-sync timestamp per row would add another query.
            $health = $factory
                ->fromRow($row, $cache, null)
                ->withLogPath($this->logPaths->resolve($deviceId));

            if (!$this->matches($health, $criteria)) {
                continue;
            }

            $devices[] = $health;
        }

        $summary = FleetSummary::fromDevices($devices, $now);
        $this->sort($devices, $criteria->sort);

        if ($criteria->limit > 0) {
            $devices = array_slice($devices, 0, $criteria->limit);
        }

        return new FleetSnapshot($summary, $devices, $now);
    }

    public function device(string $user, string $deviceId): DeviceHealth
    {
        foreach ($this->state->listDevices(
            $user,
            ['device_id' => $deviceId]
        ) as $row) {
            if ((string) ($row['device_id'] ?? '') !== $deviceId
                || (string) ($row['device_user'] ?? '') !== $user) {
                continue;
            }

            $cache = $this->state->getSyncCache($deviceId, $user);
            $lastSync = $this->state->getLastSyncTimestamp($deviceId, $user);
            $now = ($this->clock)();
            $factory = ($this->factoryBuilder)(
                (new SnapshotCriteria())->toHealthOptions($now)
            );

            return $factory
                ->fromRow($row, $cache, $lastSync)
                ->withLogPath($this->logPaths->resolve($deviceId));
        }

        throw new Horde_Exception_NotFound(
            sprintf('ActiveSync device %s was not found for %s.', $deviceId, $user)
        );
    }

    private function matches(
        DeviceHealth $health,
        SnapshotCriteria $criteria
    ): bool {
        if ($criteria->healthMin !== null
            && HealthStatus::rank($health->status)
                < HealthStatus::rank($criteria->healthMin)) {
            return false;
        }

        return !$criteria->stuckOnly || $health->isStuck();
    }

    /**
     * @param DeviceHealth[] $devices
     */
    private function sort(array &$devices, string $sort): void
    {
        usort(
            $devices,
            match ($sort) {
                SnapshotCriteria::SORT_USER =>
                    static fn (DeviceHealth $a, DeviceHealth $b): int =>
                        strnatcasecmp($a->user, $b->user)
                            ?: strnatcasecmp($a->deviceId, $b->deviceId),
                SnapshotCriteria::SORT_HEALTH =>
                    static fn (DeviceHealth $a, DeviceHealth $b): int =>
                        HealthStatus::rank($b->status)
                            <=> HealthStatus::rank($a->status)
                            ?: self::compareAge($a, $b),
                SnapshotCriteria::SORT_DEVICE =>
                    static fn (DeviceHealth $a, DeviceHealth $b): int =>
                        strnatcasecmp($a->deviceId, $b->deviceId),
                default =>
                    static fn (DeviceHealth $a, DeviceHealth $b): int =>
                        self::compareAge($a, $b),
            }
        );
    }

    private static function compareAge(
        DeviceHealth $a,
        DeviceHealth $b
    ): int {
        if ($a->ageSeconds === null) {
            return $b->ageSeconds === null ? 0 : 1;
        }
        if ($b->ageSeconds === null) {
            return -1;
        }

        return $a->ageSeconds <=> $b->ageSeconds;
    }
}
