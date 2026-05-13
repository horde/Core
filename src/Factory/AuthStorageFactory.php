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

namespace Horde\Core\Factory;

use Horde\Auth\LockManager;
use Horde\Auth\LoginAttemptTracker;
use Horde\Core\Auth\Storage\HistoryAttemptTracker;
use Horde\Core\Auth\Storage\HordeLockAdapter;
use Horde_History;
use Horde\Injector\Injector;
use Horde_Lock;

/**
 * Factory for auth-related storage adapters.
 */
class AuthStorageFactory
{
    public function createLockManager(Injector $injector): LockManager
    {
        $lock = $injector->getInstance(Horde_Lock::class);

        return new HordeLockAdapter($lock);
    }

    public function createAttemptTracker(Injector $injector): LoginAttemptTracker
    {
        $history = $injector->getInstance(Horde_History::class);

        return new HistoryAttemptTracker($history);
    }
}
