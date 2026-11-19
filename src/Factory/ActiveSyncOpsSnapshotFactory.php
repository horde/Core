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

namespace Horde\Core\Factory;

use Horde\ActiveSync\Ops\HealthEvaluator;
use Horde\Core\ActiveSync\Ops\DeviceLogPathResolver;
use Horde\Core\ActiveSync\Ops\SnapshotService;
use Horde\Injector\Injector;
use Horde_Exception;
use Horde_Injector;

final class ActiveSyncOpsSnapshotFactory
{
    public function create(Horde_Injector|Injector $injector): SnapshotService
    {
        global $conf;

        if (!class_exists(HealthEvaluator::class)) {
            throw new Horde_Exception('ActiveSync Ops support is not installed.');
        }
        if (empty($conf['activesync']['enabled'])) {
            throw new Horde_Exception('ActiveSync is disabled.');
        }

        $logging = $conf['activesync']['logging'] ?? [];

        return new SnapshotService(
            state: $injector->get('Horde_ActiveSyncState'),
            logPaths: new DeviceLogPathResolver(
                is_string($logging['type'] ?? null)
                    ? $logging['type']
                    : null,
                is_string($logging['path'] ?? null)
                    ? $logging['path']
                    : null
            ),
            logger: $injector->get('Horde_Log_Logger')
        );
    }
}
