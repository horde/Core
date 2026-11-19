<?php

/**
 * Null implementation of ServiceCredentialManager.
 *
 * Throws exceptions for all operations.
 * Used when unified credential system is not configured.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

use RuntimeException;

final class NullServiceCredentialManager implements ServiceCredentialManager
{
    public function get(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ServiceCredentialResult {
        throw new RuntimeException('Service credential manager not available');
    }

    public function initiate(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): CredentialAcquisitionResult {
        return new CredentialAcquisitionResult(AcquisitionAction::Unavailable);
    }

    public function revoke(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): void {
        // No-op
    }
}
