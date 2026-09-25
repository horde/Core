<?php

declare(strict_types=1);

namespace Horde\Core\Service;

use Horde\OAuth\Client\OAuthFlowData;
use Horde\OAuth\Client\ScopeSet;
use Psr\Http\Message\UriInterface;

/** Null ServiceAuthorizationService - safe default when service authorization is not configured. */
class NullServiceAuthorizationService implements ServiceAuthorizationService
{
    public function get(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
    ): ServiceAuthorization {
        throw new ServiceNotAuthorizedException(
            $userId,
            $providerId,
            $purpose,
            new ScopeSet()
        );
    }

    public function initiate(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
        string $returnUrl,
        ?string $requestingApp = null,
    ): ?UriInterface {
        return null;
    }

    public function handleCallback(string $code, OAuthFlowData $flowData): ServiceAuthorization
    {
        throw new \RuntimeException('Service authorization not configured');
    }

    public function revoke(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
    ): void {}

    public function revokeAll(
        string $userId,
        string $providerId,
        bool $revokeAtProvider = false,
    ): void {}
}
