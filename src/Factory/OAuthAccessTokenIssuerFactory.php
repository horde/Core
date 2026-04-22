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

use Horde\Jwt\Signer\Rs256Signer;
use Horde\Jwt\TokenEncoder;
use Horde\OAuth\Server\Repository\AccessTokenRepository;
use Horde\OAuth\Server\Token\AccessTokenIssuer;
use Horde_Injector;

class OAuthAccessTokenIssuerFactory
{
    public function create(Horde_Injector $injector): AccessTokenIssuer
    {
        $conf = $GLOBALS['conf'] ?? [];
        $oauthConf = $conf['oauth_server'] ?? [];
        $issuer = $oauthConf['issuer'] ?? $GLOBALS['registry']->get('webroot', 'horde');
        $ttl = (int) ($oauthConf['access_token_ttl'] ?? 3600);

        return new AccessTokenIssuer(
            $injector->getInstance(TokenEncoder::class),
            $injector->getInstance(Rs256Signer::class),
            $injector->getInstance(AccessTokenRepository::class),
            $issuer,
            $ttl,
        );
    }
}
