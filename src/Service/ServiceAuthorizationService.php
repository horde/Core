<?php
declare(strict_types=1);

namespace Horde\Core\Service;

use Psr\Http\Message\UriInterface;

interface ServiceAuthorizationService
{
    /**
     * Return an existing and satisfied ServiceAuthorization.
     *
     * @throws ServiceNotAuthorizedException  no authorization or grant does
     *                                        not cover the required scopes.
     *                                        The exception carries the purpose
     *                                        and providerId. The caller can react by calling initiate().
     */
    public function get(
        string         $userId,
        string         $providerId,
        ServicePurpose $purpose,
    ): ServiceAuthorization;

    /**
     * Initiate an OAuth2 PKCE flow to establish a ServiceAuthorization.
     *
     * Additive strategy: if a shared grant already covers the required scopes,
     * creates the ServiceAuthorization directly and returns null (no redirect).
     *
     * Isolated strategy: always starts a new flow. Preferred strategy.
     *
     * @throws UnsupportedPurposeException  the provider has no scope mapping
     *                                      for this purpose.
     * @return UriInterface|null  provider redirect URI, or null if no flow needed.
     */
    public function initiate(
        string         $userId,
        string         $providerId,
        ServicePurpose $purpose,
        string         $returnUrl,
        ?string        $requestingApp = null,
    ): ?UriInterface;

    /**
     * Handle the provider callback after PKCE state verification.
     * Creates or updates the TokenGrant and creates the ServiceAuthorization.
     * Does NOT call IdentityLinkService as that remains the caller's
     * responsibility for 'login' and 'account_link' purposes.
     */
    public function handleCallback(string $code, OAuthFlowData $flowData): ServiceAuthorization;

    /**
     * Revoke a single ServiceAuthorization.
     * The backing TokenGrant is not deleted.
     */
    public function revoke(
        string         $userId,
        string         $providerId,
        ServicePurpose $purpose,
    ): void;

    /**
     * Revoke all ServiceAuthorizations for a provider and delete all backing
     * TokenGrants.
     *
     * @param bool $revokeAtProvider  True: Call the provider's revocation
     *                                endpoint for each grant before deletion.
     */
    public function revokeAll(
        string $userId,
        string $providerId,
        bool   $revokeAtProvider = false,
    ): void;
}
