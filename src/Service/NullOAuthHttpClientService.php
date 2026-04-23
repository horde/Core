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
use Horde\OAuth\Client\AuthenticatedHttpClient;

/**
 * Null implementation — always throws. Safe default when OAuth is not configured.
 */
class NullOAuthHttpClientService implements OAuthHttpClientService
{
    public function getClient(
        string $userId,
        string $providerId,
        ?WantedScopes $wantedScopes = null,
    ): AuthenticatedHttpClient {
        throw new OAuthTokenNotFoundException(
            "OAuth HTTP client not available (no token service configured)"
        );
    }
}
