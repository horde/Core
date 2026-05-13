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

use Horde\OAuth\Server\Grant\AuthorizationCodeGrant;
use Horde\OAuth\Server\Repository\AuthorizationCodeRepository;
use Horde\OAuth\Server\Repository\ScopeRepository;
use Horde\OAuth\Server\Token\AccessTokenIssuer;
use Horde\OAuth\Server\Token\RefreshTokenIssuer;
use Horde\Injector\Injector;

class OAuthAuthorizationCodeGrantFactory
{
    public function create(Injector $injector): AuthorizationCodeGrant
    {
        return new AuthorizationCodeGrant(
            $injector->getInstance(AuthorizationCodeRepository::class),
            $injector->getInstance(AccessTokenIssuer::class),
            $injector->getInstance(RefreshTokenIssuer::class),
            $injector->getInstance(ScopeRepository::class),
        );
    }
}
