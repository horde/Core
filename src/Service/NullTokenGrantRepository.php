<?php

declare(strict_types=1);

namespace Horde\Core\Service;

/** Null TokenGrantRepository - safe default when service authorization is not configured. */
class NullTokenGrantRepository implements TokenGrantRepository
{
    public function findShared(string $userId, string $providerId): ?TokenGrant
    {
        return null;
    }

    public function findById(string $grantId): ?TokenGrant
    {
        return null;
    }

    public function findAll(string $userId, string $providerId): array
    {
        return [];
    }

    public function save(TokenGrant $grant): void {}

    public function update(TokenGrant $grant): void {}

    public function delete(TokenGrant $grant): void {}
}
