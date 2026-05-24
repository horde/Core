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
use Horde\Core\Config\RegistryState;
use Horde\Core\RuntimeRoutesProvider;
use Horde\Http\RequestFactory;
use Horde\Injector\Injector;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Factory for RuntimeRoutesProvider.
 *
 * Works in both the Rampage path (RegistryState and ServerRequestInterface
 * already set) and the legacy path (derives them from RegistryConfigLoader
 * and server globals).
 */
class RuntimeRoutesProviderFactory
{
    public function create(Injector $injector): RuntimeRoutesProvider
    {
        if ($injector->has(RegistryState::class)) {
            $registryState = $injector->getInstance(RegistryState::class);
        } else {
            $loader = $injector->getInstance(RegistryConfigLoader::class);
            $registryState = $loader->load();
            $injector->setInstance(RegistryState::class, $registryState);
        }

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
