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

use Horde\OAuth\Server\Handler\AuthorizationEndpoint;
use Horde\OAuth\Server\Repository\AuthorizationCodeRepository;
use Horde\OAuth\Server\Repository\ClientRepository;
use Horde\OAuth\Server\Repository\ScopeRepository;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde\Injector\Injector;

class OAuthAuthorizationEndpointFactory
{
    public function create(Injector $injector): AuthorizationEndpoint
    {
        return new AuthorizationEndpoint(
            $injector->getInstance(ClientRepository::class),
            $injector->getInstance(ScopeRepository::class),
            $injector->getInstance(AuthorizationCodeRepository::class),
            $injector->getInstance(ResponseFactoryInterface::class),
            $injector->getInstance(StreamFactoryInterface::class),
        );
    }
}
