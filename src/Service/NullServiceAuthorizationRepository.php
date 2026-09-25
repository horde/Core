<?php

declare(strict_types=1);

namespace Horde\Core\Service;

/** Null ServiceAuthorizationRepository - safe default when service authorization is not configured. */
class NullServiceAuthorizationRepository implements ServiceAuthorizationRepository
{
    public function find(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
    ): ?ServiceAuthorization {
        return null;
    }

    public function findAll(string $userId, string $providerId): array
    {
        return [];
    }

    public function findAllForUser(string $userId): array
    {
        return [];
    }

    public function save(ServiceAuthorization $authorization): void {}

    public function delete(ServiceAuthorization $authorization): void {}

    public function deleteAll(string $userId, string $providerId): void {}
}
