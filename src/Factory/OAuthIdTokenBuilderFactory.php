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
use Horde\OAuth\Oidc\ClaimsMapper;
use Horde\OAuth\Oidc\IdTokenBuilder;
use Horde\OAuth\Oidc\ScopeClaimsMapping;
use Horde_Injector;

class OAuthIdTokenBuilderFactory
{
    public function create(Horde_Injector $injector): IdTokenBuilder
    {
        $conf = $GLOBALS['conf'] ?? [];
        $oauthConf = $conf['oauth_server'] ?? [];
        $issuer = $oauthConf['issuer'] ?? $GLOBALS['registry']->get('webroot', 'horde');
        $ttl = (int) ($oauthConf['access_token_ttl'] ?? 3600);

        return new IdTokenBuilder(
            $injector->getInstance(TokenEncoder::class),
            $injector->getInstance(Rs256Signer::class),
            $injector->getInstance(ClaimsMapper::class),
            $injector->getInstance(ScopeClaimsMapping::class),
            $issuer,
            $ttl,
        );
    }
}
