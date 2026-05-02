<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license http://www.horde.org/licenses/lgpl21 LGPL-2.1
 */

namespace Horde\Core\Auth\Storage;

use Horde\Auth\LoginAttemptTracker;
use Horde_History;

/**
 * Adapts the legacy Horde_History service to the LoginAttemptTracker interface.
 *
 * Each user gets a history GUID. Failed attempts are logged as 'auth_failure'
 * actions. A successful login is logged as 'auth_success' and the failure
 * count is derived by counting failures since the last success.
 */
class HistoryAttemptTracker implements LoginAttemptTracker
{
    public function __construct(
        private readonly Horde_History $history,
        private readonly string $guidPrefix = 'horde:auth:attempt:',
    ) {}

    public function recordFailure(string $userId): void
    {
        $this->history->log(
            $this->guidPrefix . $userId,
            ['action' => 'auth_failure']
        );
    }

    public function getFailureCount(string $userId): int
    {
        $guid = $this->guidPrefix . $userId;

        $lastSuccess = $this->history->getActionTimestamp($guid, 'auth_success');

        if ($lastSuccess > 0) {
            $entries = $this->history->getByTimestamp(
                '>',
                $lastSuccess,
                [['field' => 'action', 'op' => '=', 'value' => 'auth_failure']],
                $guid
            );

            return count($entries);
        }

        $entries = $this->history->getByTimestamp(
            '>',
            0,
            [['field' => 'action', 'op' => '=', 'value' => 'auth_failure']],
            $guid
        );

        return count($entries);
    }

    public function resetFailures(string $userId): void
    {
        $this->history->log(
            $this->guidPrefix . $userId,
            ['action' => 'auth_success']
        );
    }
}
