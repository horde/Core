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
use Horde\Core\Service\NullOAuthTokenRepository;
use Horde\Core\Service\OAuthTokenRepository;
use Horde\Injector\Injector;

/**
 * Factory for OAuthTokenRepository.
 *
 * Returns the configured repository implementation. Defaults to
 * NullOAuthTokenRepository when no storage backend is configured.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class OAuthTokenRepositoryFactory
{
    public function create(Injector $injector): OAuthTokenRepository
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        $driver = $state->get('oauth.token_driver', 'null');

        return match (strtolower($driver)) {
            'null', '' => new NullOAuthTokenRepository(),
            default => new NullOAuthTokenRepository(),
        };
    }
}
