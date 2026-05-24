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
use Horde\Core\RuntimeRoutesProvider;
use Horde\Http\RequestFactory;
use Horde\Injector\Injector;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Factory for RuntimeRoutesProvider.
 *
 * Derives RegistryState from RegistryConfigLoader (cached internally).
 * Builds ServerRequestInterface from globals when not already set.
 */
class RuntimeRoutesProviderFactory
{
    public function create(Injector $injector): RuntimeRoutesProvider
    {
        $registryState = $injector->getInstance(RegistryConfigLoader::class)->load();

        if ($injector->has(ServerRequestInterface::class)) {
            $request = $injector->getInstance(ServerRequestInterface::class);
        } else {
            $factory = new RequestFactory();
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $request = $factory->createServerRequest($method, $uri, $_SERVER);
            $injector->setInstance(ServerRequestInterface::class, $request);
        }

        $provider = new RuntimeRoutesProvider($registryState, $request);
        $provider->loadAllApps();

        return $provider;
    }
}
