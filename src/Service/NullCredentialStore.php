<?php

/**
 * Null implementation of CredentialStore.
 *
 * Returns null/empty for all find operations, no-ops for mutations.
 * Used when password credential system is not configured.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

use RuntimeException;

final class NullCredentialStore implements CredentialStore
{
    public function find(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ?PasswordCredential {
        return null;
    }

    public function findAll(string $userId): array
    {
        return [];
    }

    public function findAllForProvider(
        string $userId,
        string $providerId
    ): array {
        return [];
    }

    public function store(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
        array|string $credential
    ): PasswordCredential {
        throw new RuntimeException('Password credential storage not available');
    }

    public function update(
        string $credentialId,
        array|string $credential
    ): PasswordCredential {
        throw new RuntimeException('Password credential storage not available');
    }

    public function delete(string $credentialId): void
    {
        // No-op
    }

    public function deleteAll(string $userId, string $providerId): void
    {
        // No-op
    }
}
