<?php
declare(strict_types=1);

namespace Horde\Core\Service;

interface TokenGrant
{
    /** Stable UUID assigned at creation; used as FK by ServiceAuthorization. */
    public function grantId(): string;

    public function userId(): string;
    public function providerId(): string;

    /** The set of OAuth scopes that were granted by the provider. */
    public function grantedScopes(): ScopeSet;

    /**
     * Return true if grantedScopes() is a superset of $required.
     * Does not perform a network call.
     */
    public function covers(ScopeSet $required): bool;

    /**
     * Return a valid access token string.
     * Refresh transparently if expired.
     * Persists the refreshed TokenSet via TokenGrantRepository.
     *
     * @throws OAuthTokenRefreshException
     */
    public function getAccessToken(): string;

    public function isExpired(): bool;

    /** Raw token data */
    public function tokenSet(): TokenSet;
}
