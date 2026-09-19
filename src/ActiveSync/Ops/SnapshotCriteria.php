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

use Horde\ActiveSync\Ops\HealthOptions;
use Horde\ActiveSync\Ops\HealthStatus;
use InvalidArgumentException;

final class SnapshotCriteria
{
    public const SORT_AGE = 'age';
    public const SORT_USER = 'user';
    public const SORT_HEALTH = 'health';
    public const SORT_DEVICE = 'device';

    public function __construct(
        public readonly ?string $user = null,
        public readonly ?string $deviceId = null,
        public readonly ?int $activeWithin = null,
        public readonly ?string $healthMin = null,
        public readonly bool $stuckOnly = false,
        public readonly int $limit = 100,
        public readonly string $sort = self::SORT_AGE
    ) {
        if ($healthMin !== null && !HealthStatus::isValid($healthMin)) {
            throw new InvalidArgumentException(
                sprintf('Unknown health status: %s', $healthMin)
            );
        }

        if (!in_array($sort, [
            self::SORT_AGE,
            self::SORT_USER,
            self::SORT_HEALTH,
            self::SORT_DEVICE,
        ], true)) {
            throw new InvalidArgumentException(
                sprintf('Unknown snapshot sort: %s', $sort)
            );
        }

        if ($limit < 0) {
            throw new InvalidArgumentException('Snapshot limit cannot be negative.');
        }
    }

    public function toHealthOptions(int $now): HealthOptions
    {
        if ($this->activeWithin === null) {
            return new HealthOptions(now: $now);
        }

        return new HealthOptions(
            now: $now,
            activeWithin: $this->activeWithin
        );
    }
}
