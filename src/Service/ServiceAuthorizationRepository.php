<?php
declare(strict_types=1);

namespace Horde\Core\Service;

interface ServiceAuthorizationRepository
{
    public function find(
        string         $userId,
        string         $providerId,
        ServicePurpose $purpose,
    ): ?ServiceAuthorization;

    /** All authorizations for a (userId, providerId) pair. */
    public function findAll(string $userId, string $providerId): array;

    /** All authorizations for a user across all providers. */
    public function findAllForUser(string $userId): array;

    public function save(ServiceAuthorization $authorization): void;

    public function delete(ServiceAuthorization $authorization): void;

    /** Remove all authorizations for a (userId, providerId) pair. */
    public function deleteAll(string $userId, string $providerId): void;
}
