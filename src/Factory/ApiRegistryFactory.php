<?php

declare(strict_types=1);

namespace Horde\Core\Factory;

use Horde\Core\Api\ApiInterfaceListProvider;
use Horde\Core\Api\ApiRegistry;
use Horde\Core\Config\RegistryConfigLoader;
use Horde\Rpc\Dispatch\ApiProvider;
use Horde\Rpc\Dispatch\MethodInvoker;
use Horde_Injector;
use Throwable;

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */
class ApiRegistryFactory
{
    public function create(Horde_Injector $injector): ApiRegistry
    {
        $registry = new ApiRegistry();
        $registryLoader = $injector->getInstance(RegistryConfigLoader::class);
        $state = $registryLoader->load();

        foreach ($state->listApplications() as $appName) {
            $className = 'Horde\\' . ucfirst($appName) . '\\Api';

            if (!class_exists($className)) {
                continue;
            }

            if (!is_subclass_of($className, ApiInterfaceListProvider::class)) {
                continue;
            }

            try {
                $appInstance = $injector->getInstance($className);
                $interfaces = $appInstance->getApiInterfaceList();
            } catch (Throwable) {
                continue;
            }

            foreach ($interfaces as $interface => $providerClass) {
                try {
                    $provider = $injector->getInstance($providerClass);
                } catch (Throwable) {
                    continue;
                }
                if ($provider instanceof ApiProvider && $provider instanceof MethodInvoker) {
                    $registry->registerProvider($interface, $provider, $appName);
                }
            }
        }

        return $registry;
    }
}
