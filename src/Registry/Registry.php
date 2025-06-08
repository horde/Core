<?php

namespace Horde\Core\Registry;

use Horde\Injector\Injector;
use Horde\Injector\Scope;

/**
 * Facade to the effective configuration of the Horde Registry
 *
 * - Iterate installed applications, services and APIs
 * - Setup a PSR-11 dependency injection container
 *
 */
class Registry implements Scope
{
    private $instances = [];
    // Should we just wrap the array or some provider?
    public function __construct(RegistryLoader $config = new RegistryLoader())
    {
        $this->instances[DeploymentRootDirectory::class] = new DeploymentRootDirectory(
            $config->getDeploymentRootPath()
        );
        $this->instances[ConfigRootDirectory::class] = new ConfigRootDirectory(
            $this->instances[DeploymentRootDirectory::class]->getPath() . '/var/config/'
        );

    }

    public function getBinder(string $interface): null
    {
        return null; // No binder, we are not a service provider
    }

    public function has(string $interface): bool
    {
        return array_key_exists($interface, $this->instances); // No services, we are not a service provider
    }

    public function getInstance(string $interface): mixed
    {
        return $this->get($interface);
    }

    public function get(string $interface): mixed
    {
        return false;
    }
}
