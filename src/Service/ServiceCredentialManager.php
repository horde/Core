<?php
/**
 * Unified credential manager (Entry Point B).
 *
 * Abstraction that dispatches to OAuth or Password subsystem transparently
 * based on provider configuration.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

interface ServiceCredentialManager
{
    /**
     * Retrieve credential for a purpose (agnostic to underlying system).
     */
    public function get(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ServiceCredentialResult;

    /**
     * Initiate acquisition if credential doesn't exist.
     */
    public function initiate(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): CredentialAcquisitionResult;

    /**
     * Revoke credential.
     */
    public function revoke(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): void;
}
