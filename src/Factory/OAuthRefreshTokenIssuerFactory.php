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

namespace Horde\Core\Factory;

use Horde\OAuth\Server\Repository\RefreshTokenRepository;
use Horde\OAuth\Server\Token\RefreshTokenIssuer;
use Horde_Injector;

class OAuthRefreshTokenIssuerFactory
{
    public function create(Horde_Injector $injector): RefreshTokenIssuer
    {
        $conf = $GLOBALS['conf'] ?? [];
        $oauthConf = $conf['oauth_server'] ?? [];
        $ttl = (int) ($oauthConf['refresh_token_ttl'] ?? 2592000);

        return new RefreshTokenIssuer(
            $injector->getInstance(RefreshTokenRepository::class),
            $ttl,
        );
    }
}
