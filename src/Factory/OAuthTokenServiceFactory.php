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

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Service\NullOAuthTokenService;
use Horde\Core\Service\OAuthTokenService;
use Horde_Injector;

/**
 * Factory for OAuthTokenService.
 *
 * Returns the configured token service implementation. Defaults to
 * NullOAuthTokenService when OAuth is not configured.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class OAuthTokenServiceFactory
{
    public function create(Horde_Injector $injector): OAuthTokenService
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        $driver = $state->get('oauth.token_driver', 'null');

        return match (strtolower($driver)) {
            'null', '' => new NullOAuthTokenService(),
            default => new NullOAuthTokenService(),
        };
    }
}
