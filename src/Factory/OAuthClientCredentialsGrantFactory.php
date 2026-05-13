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

use Horde\OAuth\Server\Grant\ClientCredentialsGrant;
use Horde\OAuth\Server\Repository\ScopeRepository;
use Horde\OAuth\Server\Token\AccessTokenIssuer;
use Horde\Injector\Injector;

class OAuthClientCredentialsGrantFactory
{
    public function create(Injector $injector): ClientCredentialsGrant
    {
        return new ClientCredentialsGrant(
            $injector->getInstance(AccessTokenIssuer::class),
            $injector->getInstance(ScopeRepository::class),
        );
    }
}
