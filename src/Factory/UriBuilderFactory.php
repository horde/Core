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

use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Uri\RouteMapperProvider;
use Horde\Core\Uri\UriBuilder;
use Horde\Core\Uri\UriBuilderInterface;
use Horde\Injector\Injector;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class UriBuilderFactory
{
    public function create(Injector $injector): UriBuilderInterface
    {
        $registryLoader = $injector->getInstance(RegistryConfigLoader::class);
        $registryState = $registryLoader->load();

        $routeProvider = $injector->getInstance(RouteMapperProvider::class);

        $request = null;
        try {
            $request = $injector->getInstance(ServerRequestInterface::class);
        } catch (Throwable) {
        }

        return new UriBuilder($registryState, $routeProvider, $request);
    }
}
