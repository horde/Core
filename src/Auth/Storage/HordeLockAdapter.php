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

use DateTimeImmutable;
use Horde\Auth\LockManager;
use Horde_Lock;

/**
 * Adapts the legacy Horde_Lock service to the LockManager interface.
 *
 * Uses exclusive locks scoped to auth lockout. Each user's lockout is
 * represented by a single exclusive lock keyed on the userId as principal.
 */
class HordeLockAdapter implements LockManager
{
    public function __construct(
        private readonly Horde_Lock $lock,
        private readonly string $scope = 'horde:auth:lockout',
        private readonly string $requestor = 'auth_system',
    ) {}

    public function lock(string $userId, int $duration = 0): void
    {
        $this->clearExistingLocks($userId);

        $lifetime = $duration > 0 ? $duration : Horde_Lock::PERMANENT;

        $this->lock->setLock(
            $this->requestor,
            $this->scope,
            $userId,
            $lifetime,
            Horde_Lock::TYPE_EXCLUSIVE
        );
    }

    public function unlock(string $userId): void
    {
        $this->clearExistingLocks($userId);
    }

    public function isLocked(string $userId): bool
    {
        $locks = $this->lock->getLocks(
            $this->scope,
            $userId,
            Horde_Lock::TYPE_EXCLUSIVE
        );

        return !empty($locks);
    }

    public function getLockInfo(string $userId): ?array
    {
        $locks = $this->lock->getLocks(
            $this->scope,
            $userId,
            Horde_Lock::TYPE_EXCLUSIVE
        );

        if (empty($locks)) {
            return null;
        }

        $lockData = reset($locks);

        $lockedAt = new DateTimeImmutable('@' . $lockData['lock_update_timestamp']);

        $expiresAt = null;
        if (isset($lockData['lock_expiry_timestamp']) && $lockData['lock_expiry_timestamp'] > 0) {
            $expiresAt = new DateTimeImmutable('@' . $lockData['lock_expiry_timestamp']);
        }

        return [
            'locked_at' => $lockedAt,
            'expires_at' => $expiresAt,
        ];
    }

    private function clearExistingLocks(string $userId): void
    {
        $locks = $this->lock->getLocks(
            $this->scope,
            $userId,
            Horde_Lock::TYPE_EXCLUSIVE
        );

        foreach ($locks as $lockId => $lockData) {
            $this->lock->clearLock($lockId);
        }
    }
}
