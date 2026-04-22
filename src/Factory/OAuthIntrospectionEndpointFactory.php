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

use Horde\OAuth\Server\ClientAuthentication\ClientAuthenticatorChain;
use Horde\OAuth\Server\Handler\IntrospectionEndpoint;
use Horde\OAuth\Server\Repository\AccessTokenRepository;
use Horde\OAuth\Server\Repository\RefreshTokenRepository;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde_Injector;

class OAuthIntrospectionEndpointFactory
{
    public function create(Horde_Injector $injector): IntrospectionEndpoint
    {
        return new IntrospectionEndpoint(
            $injector->getInstance(ClientAuthenticatorChain::class),
            $injector->getInstance(AccessTokenRepository::class),
            $injector->getInstance(RefreshTokenRepository::class),
            $injector->getInstance(ResponseFactoryInterface::class),
            $injector->getInstance(StreamFactoryInterface::class),
        );
    }
}
