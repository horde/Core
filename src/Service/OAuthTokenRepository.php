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

use Horde\Core\Factory\OAuthTokenRepositoryFactory;
use Horde\Injector\Attribute\Factory;
use Horde\Oauth\Client\TokenSet;

/**
 * Pure storage contract for OAuth2 token sets.
 *
 * Handles persistence of TokenSet objects keyed by (userId, providerId).
 * No business logic — no expiry checks, no token refresh.
 * Business logic lives in OAuthTokenService which composes a repository.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[Factory(factory: OAuthTokenRepositoryFactory::class, method: 'create')]
interface OAuthTokenRepository
{
    /**
     * Load a stored token set.
     *
     * @param string $userId Horde user ID
     * @param string $providerId Provider identifier (e.g. 'google', 'microsoft')
     * @return TokenSet The stored token set
     * @throws Exception\OAuthTokenNotFoundException When no tokens exist
     */
    public function load(string $userId, string $providerId): TokenSet;

    /**
     * Save a token set, replacing any existing one for this user+provider.
     *
     * @param string $userId Horde user ID
     * @param string $providerId Provider identifier
     * @param TokenSet $tokens Token set to store
     */
    public function save(string $userId, string $providerId, TokenSet $tokens): void;

    /**
     * Check if tokens exist for a user+provider pair.
     *
     * @param string $userId Horde user ID
     * @param string $providerId Provider identifier
     */
    public function exists(string $userId, string $providerId): bool;

    /**
     * Delete stored tokens for a user+provider pair.
     *
     * @param string $userId Horde user ID
     * @param string $providerId Provider identifier
     */
    public function delete(string $userId, string $providerId): void;
}
