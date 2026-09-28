<?php
/**
 * Result of unified credential retrieval.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

final class ServiceCredentialResult
{
    public function __construct(
        public readonly CredentialType $type,
        public readonly TokenGrant|PasswordCredential $credential,
    ) {}

    /**
     * Convenience accessor for OAuth token.
     */
    public function asOAuthToken(): ?string
    {
        return $this->type === CredentialType::OAuth
            ? $this->credential->getAccessToken()
            : null;
    }

    /**
     * Convenience accessor for password credential.
     */
    public function asPassword(): ?PasswordCredential
    {
        return $this->type === CredentialType::Password
            ? $this->credential
            : null;
    }
}
