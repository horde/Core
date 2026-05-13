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

use Horde\OAuth\Server\Grant\RefreshTokenGrant;
use Horde\OAuth\Server\Repository\AccessTokenRepository;
use Horde\OAuth\Server\Repository\RefreshTokenRepository;
use Horde\OAuth\Server\Repository\ScopeRepository;
use Horde\OAuth\Server\Token\AccessTokenIssuer;
use Horde\OAuth\Server\Token\RefreshTokenIssuer;
use Horde\Injector\Injector;

class OAuthRefreshTokenGrantFactory
{
    public function create(Injector $injector): RefreshTokenGrant
    {
        return new RefreshTokenGrant(
            $injector->getInstance(RefreshTokenRepository::class),
            $injector->getInstance(AccessTokenRepository::class),
            $injector->getInstance(AccessTokenIssuer::class),
            $injector->getInstance(RefreshTokenIssuer::class),
            $injector->getInstance(ScopeRepository::class),
        );
    }
}
