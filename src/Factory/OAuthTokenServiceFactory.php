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

use Horde\Core\Service\NullOAuthTokenService;
use Horde\Core\Service\OAuthTokenService;
use Horde\Injector\Injector;

/**
 * Factory for OAuthTokenService.
 *
 * Returns NullOAuthTokenService as a safe fallback. When a storage backend
 * is configured (e.g. 'sql'), the horde/base package overrides this binding
 * with its own factory that creates DefaultOAuthTokenService.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class OAuthTokenServiceFactory
{
    public function create(Injector $injector): OAuthTokenService
    {
        return new NullOAuthTokenService();
    }
}
