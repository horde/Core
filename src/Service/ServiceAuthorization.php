<?php
declare(strict_types=1);

namespace Horde\Core\Service;
/**
 * Revocation is performed through `ServiceAuthorizationService` rather than through this object.
 * This avoids the object holding a self-reference to its repository.
 */
interface ServiceAuthorization
{
    public function userId(): string;
    public function providerId(): string;
    public function purpose(): ServicePurpose;
    public function grant(): TokenGrant;

    /**
     * True if the backing grant is non-null and covers the scopes required
     * for this purpose.  Does not perform a network call.
     */
    public function isSatisfied(): bool;

    /**
     * Return a valid access token delegating to grant()->getAccessToken().
     *
     * @throws ServiceNotAuthorizedException  when !isSatisfied()
     * @throws OAuthTokenRefreshException     when the grant refresh fails
     */
    public function getAccessToken(): string;
}
