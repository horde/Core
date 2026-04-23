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

use Horde\Core\Factory\OAuthHttpClientServiceFactory;
use Horde\Core\Service\Exception\OAuthInsufficientScopeException;
use Horde\Core\Service\Exception\OAuthTokenNotFoundException;
use Horde\Injector\Attribute\Factory;
use Horde\OAuth\Client\AuthenticatedHttpClient;

/**
 * Provides a ready-to-use AuthenticatedHttpClient for a given user+provider.
 *
 * Loads the stored token, builds a TokenRefresher from provider config,
 * and wraps a PSR-18 client with Bearer auth and transparent 401 refresh.
 *
 * Callers should check wasRefreshed() after use and persist the updated
 * TokenSet via OAuthTokenService::store() if needed.
 */
#[Factory(factory: OAuthHttpClientServiceFactory::class, method: 'create')]
interface OAuthHttpClientService
{
    /**
     * @throws OAuthTokenNotFoundException No token stored for this user+provider
     * @throws OAuthInsufficientScopeException Token exists but lacks required scopes
     */
    public function getClient(
        string $userId,
        string $providerId,
        ?WantedScopes $wantedScopes = null,
    ): AuthenticatedHttpClient;
}
