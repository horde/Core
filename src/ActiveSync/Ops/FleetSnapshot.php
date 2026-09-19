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

use Horde\ActiveSync\Ops\DeviceHealth;
use Horde\ActiveSync\Ops\FleetSummary;

final class FleetSnapshot
{
    /**
     * @param DeviceHealth[] $devices
     */
    public function __construct(
        public readonly FleetSummary $summary,
        public readonly array $devices,
        public readonly int $asOf
    ) {
    }

    public function toArray(): array
    {
        return [
            'summary' => $this->summary->toArray(),
            'devices' => array_map(
                static fn (DeviceHealth $device): array => $device->toArray(),
                $this->devices
            ),
            'asOf' => $this->asOf,
        ];
    }
}
