<?php
/**
 * Service interface for password-based authorization.
 *
 * Coordinates credential retrieval and provisioning for password-based
 * authentication systems (username/password, API keys, bearer tokens).
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

interface PasswordServiceAuthorizationService
{
    /**
     * Retrieve existing credential.
     *
     * @throws PasswordCredentialNotFoundException
     */
    public function get(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): PasswordCredential;

    /**
     * Initiate provisioning if credential doesn't exist.
     */
    public function initiate(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ProvisioningResult;

    /**
     * Store credential (from form submission).
     *
     * @param array|string $credential Array for structured, string for opaque
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

    /**
     * Delete credential for specific purpose.
     */
    public function revoke(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): void;

    /**
     * Delete all credentials for provider.
     */
    public function revokeAll(string $userId, string $providerId): void;
}
