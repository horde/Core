<?php

/**
 * Strategy interface for credential provisioning.
 *
 * Defines how credentials get into the system initially.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

interface CredentialProvisioningStrategy
{
    /**
     * Attempt to provision credentials for given context.
     */
    public function provision(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ProvisioningResult;
}
