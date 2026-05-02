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
use Horde\Auth\AccessPolicy;
use Horde\Auth\AuthResultFail;
use Horde\Auth\AuthResultSuccess;
use Horde\Auth\CredentialProvider;
use Horde\Auth\UserDirectory;
use Horde\Auth\UserEntry;
use Horde\Auth\UserLifecycleManager;
use Horde\Core\Factory\AuthServiceFactory;
use Horde\Horde\Service\AuthLink;
use Horde\Injector\Attribute\Factory;

/**
 * Core authentication service orchestrating credential validation,
 * access policy, and identity resolution.
 *
 * This is the main entry point for authentication in Horde. It
 * composes a CredentialProvider, AccessPolicy, and IdentityBridgeService
 * to produce a full AuthenticationOutcome.
 */
#[Factory(factory: AuthServiceFactory::class, method: 'create')]
class AuthService
{
    public function __construct(
        private readonly CredentialProvider $provider,
        private readonly AccessPolicy $policy,
        private readonly IdentityBridgeService $identityBridge,
    ) {}

    /**
     * Full authentication flow: policy → credentials → identity resolution.
     *
     * Returns an AuthenticationOutcome carrying the identity ID on success,
     * or denial/failure details otherwise.
     */
    public function authenticate(string $userId, array $credentials): AuthenticationOutcome
    {
        $preDecision = $this->policy->preAuth($userId);
        if ($preDecision->isDenied()) {
            return AuthenticationOutcome::denied($preDecision);
        }

        $result = $this->provider->validate($userId, $credentials);

        $postDecision = $this->policy->postAuth($userId, $result);
        if ($postDecision->isDenied()) {
            return AuthenticationOutcome::denied($postDecision, $result);
        }

        if ($result instanceof AuthResultFail) {
            return AuthenticationOutcome::failed($result);
        }

        $identity = $this->identityBridge->resolveOrCreate(
            $result->getBackend(),
            $result->getNativeKey(),
            $result->get('mail'),
            $result->get('displayName'),
        );

        $policyForOutcome = $postDecision->requiresAction() ? $postDecision : null;

        return AuthenticationOutcome::success($result, $identity->id, $policyForOutcome);
    }

    /**
     * Credential check without identity resolution or session creation.
     *
     * Suitable for HTTP Basic auth, token validation, or any context
     * where only credential validity matters.
     */
    public function checkCredentials(string $userId, array $credentials): CredentialCheckResult
    {
        $outcome = $this->authenticate($userId, $credentials);

        return $outcome->toCredentialCheckResult();
    }

    /**
     * Check if the underlying provider supports user listing.
     */
    public function supportsListing(): bool
    {
        return $this->provider instanceof UserDirectory;
    }

    /**
     * List all users from the provider's user directory.
     *
     * @param bool $sort Sort the users alphabetically by userId
     * @return string[] Array of user IDs
     * @throws AuthNotSupportedException If backend doesn't support listing
     */
    public function listUsers(bool $sort = false): array
    {
        if (!$this->provider instanceof UserDirectory) {
            throw new AuthNotSupportedException(
                'Auth backend does not support user listing'
            );
        }

        $userIds = [];
        foreach ($this->provider->list() as $entry) {
            $userIds[] = $entry->getUserId();
        }

        if ($sort) {
            sort($userIds);
        }

        return $userIds;
    }

    /**
     * Check if user exists in the provider's user directory.
     *
     * @throws AuthNotSupportedException If backend doesn't support user lookup
     */
    public function exists(string $username): bool
    {
        if (!$this->provider instanceof UserDirectory) {
            throw new AuthNotSupportedException(
                'Auth backend does not support user lookup'
            );
        }

        return $this->provider->exists($username);
    }

    /**
     * Create a new user in the provider backend.
     *
     * @throws AuthNotSupportedException If backend doesn't support user creation
     */
    public function createUser(string $username, array $credentials): void
    {
        if (!$this->provider instanceof UserLifecycleManager) {
            throw new AuthNotSupportedException(
                'Auth backend does not support user creation'
            );
        }

        $this->provider->addUser($username, $credentials);
    }

    /**
     * Delete a user from the provider backend.
     *
     * @throws AuthNotSupportedException If backend doesn't support user deletion
     */
    public function deleteUser(string $username): void
    {
        if (!$this->provider instanceof UserLifecycleManager) {
            throw new AuthNotSupportedException(
                'Auth backend does not support user deletion'
            );
        }

        $this->provider->removeUser($username);
    }

    /**
     * Search users in the provider's user directory.
     *
     * @return string[] Array of matching user IDs
     * @throws AuthNotSupportedException If backend doesn't support searching
     */
    public function searchUsers(string $search): array
    {
        if (!$this->provider instanceof UserDirectory) {
            throw new AuthNotSupportedException(
                'Auth backend does not support user searching'
            );
        }

        $userIds = [];
        foreach ($this->provider->search($search) as $entry) {
            $userIds[] = $entry->getUserId();
        }

        return $userIds;
    }

    /**
     * Get the underlying credential provider.
     *
     * Escape hatch for operations not yet wrapped by this service.
     */
    public function getProvider(): CredentialProvider
    {
        return $this->provider;
    }

    /**
     * Get the identity bridge service for direct identity operations.
     */
    public function getIdentityBridge(): IdentityBridgeService
    {
        return $this->identityBridge;
    }
}
