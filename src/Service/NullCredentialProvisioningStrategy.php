<?php

/**
 * Null implementation of CredentialProvisioningStrategy.
 *
 * Always returns Unavailable action.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

final class NullCredentialProvisioningStrategy implements CredentialProvisioningStrategy
{
    public function provision(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ProvisioningResult {
        return new ProvisioningResult(ProvisioningAction::Unavailable);
    }
}
