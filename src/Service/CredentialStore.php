<?php
/**
 * Repository interface for password credential storage.
 *
 * Abstracts storage/retrieval from service logic. Implementations handle
 * encryption/decryption internally and return fully-decrypted credentials.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

interface CredentialStore
{
    /**
     * Find credential for specific user/provider/purpose.
     */
    public function find(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ?PasswordCredential;

    /**
     * Find all credentials for a user.
     *
     * @return PasswordCredential[]
     */
    public function findAll(string $userId): array;

    /**
     * Find all credentials for a user+provider.
     *
     * @return PasswordCredential[]
     */
    public function findAllForProvider(
        string $userId,
        string $providerId
    ): array;

    /**
     * Store new credential.
     *
     * @param array|string $credential Array for structured {'username','password'}, string for opaque
     */
    public function store(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
        array|string $credential
    ): PasswordCredential;

    /**
     * Update existing credential.
     *
     * @param array|string $credential Array for structured, string for opaque
     */
    public function update(
        string $credentialId,
        array|string $credential
    ): PasswordCredential;

    public function delete(string $credentialId): void;

    public function deleteAll(string $userId, string $providerId): void;
}
