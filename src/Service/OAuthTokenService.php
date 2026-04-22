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

use Horde\Core\Factory\OAuthTokenServiceFactory;
use Horde\Injector\Attribute\Factory;
use Horde\OAuth\Client\TokenSet;

/**
 * Centralized OAuth2 token storage and retrieval.
 *
 * Stores access/refresh tokens per user and provider. Handles transparent
 * token refresh when access tokens expire. Consumed by IMAP/SMTP for
 * XOAUTH2 authentication and by any component needing OAuth2 tokens.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[Factory(factory: OAuthTokenServiceFactory::class, method: 'create')]
interface OAuthTokenService
{
    /**
     * Get a valid access token, refreshing transparently if expired.
     *
     * @param string $userId Horde user ID
     * @param string $providerId Provider identifier (e.g. 'google', 'microsoft')
     * @return string The access token string
     * @throws Exception\OAuthTokenNotFoundException
     */
    public function getAccessToken(string $userId, string $providerId): string;

    /**
     * Store a token set after initial authorization or refresh.
     *
     * @param string $userId Horde user ID
     * @param string $providerId Provider identifier
     * @param TokenSet $tokens Token set from OAuth2 flow
     */
    public function store(string $userId, string $providerId, TokenSet $tokens): void;

    /**
     * Check if a user has stored tokens for a provider.
     *
     * @param string $userId Horde user ID
     * @param string $providerId Provider identifier
     */
    public function hasTokens(string $userId, string $providerId): bool;

    /**
     * Remove stored tokens (disconnect/revoke).
     *
     * @param string $userId Horde user ID
     * @param string $providerId Provider identifier
     */
    public function remove(string $userId, string $providerId): void;

    /**
     * Get the full token set when the caller needs more than the access token.
     *
     * @param string $userId Horde user ID
     * @param string $providerId Provider identifier
     * @return TokenSet The stored token set
     * @throws Exception\OAuthTokenNotFoundException
     */
    public function getTokenSet(string $userId, string $providerId): TokenSet;
}
