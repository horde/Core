<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license http://www.horde.org/licenses/lgpl21 LGPL-2.1
 */

namespace Horde\Core\Auth;

use Horde\Auth\AccessDecision;
use Horde\Auth\AuthResultFail;
use Horde\Auth\AuthResultSuccess;

/**
 * Immutable value object representing the full authentication flow result.
 *
 * Carries the credential result, identity resolution outcome, and any
 * policy decision. Constructed via named constructors only.
 */
class AuthenticationOutcome
{
    private function __construct(
        private readonly string $state,
        private readonly AuthResultSuccess|AuthResultFail|null $authResult,
        private readonly ?string $identityId,
        private readonly ?AccessDecision $policyDecision,
    ) {}

    /**
     * Authentication succeeded and identity was resolved.
     */
    public static function success(
        AuthResultSuccess $authResult,
        string $identityId,
        ?AccessDecision $policyDecision = null,
    ): self {
        return new self('success', $authResult, $identityId, $policyDecision);
    }

    /**
     * Credential validation failed (wrong password, unknown user).
     */
    public static function failed(AuthResultFail $authResult): self
    {
        return new self('failed', $authResult, null, null);
    }

    /**
     * Access denied by policy (locked, expired, rate limited).
     */
    public static function denied(AccessDecision $decision, AuthResultSuccess|AuthResultFail|null $authResult = null): self
    {
        return new self('denied', $authResult, null, $decision);
    }

    public function isSuccess(): bool
    {
        return $this->state === 'success';
    }

    public function isFailed(): bool
    {
        return $this->state === 'failed';
    }

    public function isDenied(): bool
    {
        return $this->state === 'denied';
    }

    public function requiresAction(): bool
    {
        return $this->policyDecision !== null && $this->policyDecision->requiresAction();
    }

    public function getRequiredAction(): ?string
    {
        return $this->policyDecision?->getAction();
    }

    public function getIdentityId(): ?string
    {
        return $this->identityId;
    }

    public function getAuthResult(): AuthResultSuccess|AuthResultFail|null
    {
        return $this->authResult;
    }

    public function getPolicyDecision(): ?AccessDecision
    {
        return $this->policyDecision;
    }

    /**
     * Map to the CredentialCheckResult enum for backward compatibility.
     */
    public function toCredentialCheckResult(): CredentialCheckResult
    {
        if ($this->isSuccess()) {
            return CredentialCheckResult::Valid;
        }

        if ($this->isDenied() && $this->policyDecision !== null) {
            $reason = $this->policyDecision->getReason();
            return match ($reason) {
                'locked' => CredentialCheckResult::Locked,
                'hard_expired', 'expired' => CredentialCheckResult::Expired,
                default => CredentialCheckResult::Invalid,
            };
        }

        return CredentialCheckResult::Invalid;
    }
}
