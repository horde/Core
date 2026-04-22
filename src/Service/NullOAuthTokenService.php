<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Service;

use Horde\Core\Service\Exception\OAuthTokenNotFoundException;
use Horde\OAuth\Client\TokenSet;

/**
 * Null OAuth token service — safe default when OAuth is not configured.
 *
 * Store is silently discarded. Any retrieval throws OAuthTokenNotFoundException.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class NullOAuthTokenService implements OAuthTokenService
{
    public function getAccessToken(string $userId, string $providerId): string
    {
        throw new OAuthTokenNotFoundException(
            "No OAuth tokens for user '{$userId}' / provider '{$providerId}'"
        );
    }

    public function store(string $userId, string $providerId, TokenSet $tokens): void {}

    public function hasTokens(string $userId, string $providerId): bool
    {
        return false;
    }

    public function remove(string $userId, string $providerId): void {}

    public function getTokenSet(string $userId, string $providerId): TokenSet
    {
        throw new OAuthTokenNotFoundException(
            "No OAuth tokens for user '{$userId}' / provider '{$providerId}'"
        );
    }
}
