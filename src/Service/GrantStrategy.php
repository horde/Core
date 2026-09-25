<?php

declare(strict_types=1);

namespace Horde\Core\Service;

enum GrantStrategy: string
{
    /**
     * Extend an existing shared TokenGrant for this (userId, providerId).
     * The new required scopes are added to the union.  The updated grant
     * backs all ServiceAuthorizations for this provider.
     */
    case Additive = 'additive';

    /**
     * Create a new, independent TokenGrant carrying only the scopes
     * required for this purpose.  No existing grant is modified.
     * This is the default case.
     */
    case Isolated = 'isolated';
}
