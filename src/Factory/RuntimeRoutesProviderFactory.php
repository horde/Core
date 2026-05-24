<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Factory;

use Horde\Core\Config\RegistryState;
use Horde\Core\RuntimeRoutesProvider;
use Horde\Injector\Injector;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Factory for RuntimeRoutesProvider.
 *
 * Creates the runtime route mapper, loads all app routes, and returns
 * the fully-compiled provider. Stateless — no globals or side effects.
 */
class RuntimeRoutesProviderFactory
{
    public function create(Injector $injector): RuntimeRoutesProvider
    {
        $registryState = $injector->getInstance(RegistryState::class);
        $request = $injector->getInstance(ServerRequestInterface::class);

        $provider = new RuntimeRoutesProvider($registryState, $request);
        $provider->loadAllApps();

        return $provider;
    }
}
