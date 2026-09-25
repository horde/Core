<?php

declare(strict_types=1);

namespace Horde\Core\Service;

use Horde\OAuth\Client\ScopeSet;

final class ServiceNotAuthorizedException extends \RuntimeException
{
    public function __construct(
        private readonly string $userId,
        private readonly string $providerId,
        private readonly ServicePurpose $purpose,
        private readonly ScopeSet $missingScopes,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if ($message === '') {
            $message = sprintf(
                "User '%s' is not authorized for purpose '%s' on provider '%s'",
                $userId,
                $purpose->identifier(),
                $providerId
            );
        }
        parent::__construct($message, $code, $previous);
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function providerId(): string
    {
        return $this->providerId;
    }

    public function purpose(): ServicePurpose
    {
        return $this->purpose;
    }

    /** Scopes still needed; empty ScopeSet if no grant exists at all. */
    public function missingScopes(): ScopeSet
    {
        return $this->missingScopes;
    }
}
