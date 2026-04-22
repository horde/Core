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

use Horde\Jwt\Key\PublicKey;
use Horde\OAuth\Oidc\Handler\JwksEndpoint;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde_Injector;

class OAuthJwksEndpointFactory
{
    public function create(Horde_Injector $injector): JwksEndpoint
    {
        $conf = $GLOBALS['conf'] ?? [];
        $oauthConf = $conf['oauth_server'] ?? [];

        return new JwksEndpoint(
            $injector->getInstance(PublicKey::class),
            $injector->getInstance(ResponseFactoryInterface::class),
            $injector->getInstance(StreamFactoryInterface::class),
            $oauthConf['key_id'] ?? 'horde-1',
            'RS256',
        );
    }
}
