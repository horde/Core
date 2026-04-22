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

use Horde\OAuth\Oidc\ClaimsMapper;
use Horde\OAuth\Oidc\Handler\UserinfoEndpoint;
use Horde\OAuth\Oidc\ScopeClaimsMapping;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde_Injector;

class OAuthUserinfoEndpointFactory
{
    public function create(Horde_Injector $injector): UserinfoEndpoint
    {
        return new UserinfoEndpoint(
            $injector->getInstance(ClaimsMapper::class),
            $injector->getInstance(ScopeClaimsMapping::class),
            $injector->getInstance(ResponseFactoryInterface::class),
            $injector->getInstance(StreamFactoryInterface::class),
        );
    }
}
