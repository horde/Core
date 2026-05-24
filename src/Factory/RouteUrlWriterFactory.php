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
use Horde\Core\Uri\RouteUrlWriter;
use Horde\Injector\Injector;

class RouteUrlWriterFactory
{
    public function create(Injector $injector): RouteUrlWriter
    {
        $provider = $injector->getInstance(RuntimeRoutesProvider::class);
        $registryState = $injector->getInstance(RegistryState::class);
        $hordeConfig = $registryState->getApplication('horde');
        $webroot = $hordeConfig['webroot'] ?? '/horde';

        return new RouteUrlWriter($provider, $provider->environ, $webroot);
    }
}
