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
 * Null token repository — safe default when OAuth storage is not configured.
 *
 * Saves are silently discarded. Any load throws OAuthTokenNotFoundException.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class NullOAuthTokenRepository implements OAuthTokenRepository
{
    public function load(string $userId, string $providerId): TokenSet
    {
        throw new OAuthTokenNotFoundException(
            "No OAuth tokens for user '{$userId}' / provider '{$providerId}'"
        );
    }

    public function save(string $userId, string $providerId, TokenSet $tokens): void {}

    public function exists(string $userId, string $providerId): bool
    {
        return false;
    }

    public function delete(string $userId, string $providerId): void {}
}
