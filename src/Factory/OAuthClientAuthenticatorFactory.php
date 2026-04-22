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
use Horde\OAuth\Server\ClientAuthentication\ClientSecretBasic;
use Horde\OAuth\Server\ClientAuthentication\ClientSecretPost;
use Horde\OAuth\Server\Repository\ClientRepository;
use Horde_Injector;

class OAuthClientAuthenticatorFactory
{
    public function create(Horde_Injector $injector): ClientAuthenticatorChain
    {
        return new ClientAuthenticatorChain(
            $injector->getInstance(ClientRepository::class),
            new ClientSecretBasic(),
            new ClientSecretPost(),
        );
    }
}
