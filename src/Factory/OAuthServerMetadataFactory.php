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

use Horde\OAuth\Server\ServerMetadata;
use Horde_Injector;

class OAuthServerMetadataFactory
{
    public function create(Horde_Injector $injector): ServerMetadata
    {
        $conf = $GLOBALS['conf'] ?? [];
        $oauthConf = $conf['oauth_server'] ?? [];

        $registry = $GLOBALS['registry'];
        $baseUrl = rtrim($oauthConf['issuer'] ?? $registry->get('webroot', 'horde'), '/');

        return new ServerMetadata(
            issuer: $baseUrl,
            authorizationEndpoint: $baseUrl . '/oauth2/authorize',
            tokenEndpoint: $baseUrl . '/oauth2/token',
            revocationEndpoint: $baseUrl . '/oauth2/revoke',
            introspectionEndpoint: $baseUrl . '/oauth2/introspect',
            jwksUri: $baseUrl . '/.well-known/jwks.json',
            userinfoEndpoint: $baseUrl . '/oauth2/userinfo',
        );
    }
}
