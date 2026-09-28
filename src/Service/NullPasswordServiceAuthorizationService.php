<?php
/**
 * Null implementation of PasswordServiceAuthorizationService.
 *
 * Throws exceptions for retrieval, returns unavailable for provisioning.
 * Used when password credential system is not configured.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

final class NullPasswordServiceAuthorizationService implements PasswordServiceAuthorizationService
{
    public function get(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): PasswordCredential {
        throw new PasswordCredentialNotFoundException($userId, $providerId, $purpose);
    }

    public function initiate(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ProvisioningResult {
        return new ProvisioningResult(ProvisioningAction::Unavailable);
    }

    public function store(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
        array|string $credential
    ): PasswordCredential {
        throw new \RuntimeException('Password credential service not available');
    }

    public function update(
        string $credentialId,
        array|string $credential
    ): PasswordCredential {
        throw new \RuntimeException('Password credential service not available');
    }

    public function revoke(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): void {
        // No-op
    }

    public function revokeAll(string $userId, string $providerId): void
    {
        // No-op
    }
}
