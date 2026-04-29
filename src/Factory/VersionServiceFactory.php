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

use Horde\Cache\Cache;
use Horde\Cache\NullStorage;
use Horde\Core\Service\ApplicationService;
use Horde\Core\Service\VersionCheck\AvailableVersionSource;
use Horde\Core\Service\VersionCheck\HordeYmlInstalledSource;
use Horde\Core\Service\VersionCheck\PackagistAvailableSource;
use Horde\Core\Service\VersionCheck\VersionService;
use Horde\Injector\Injector;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Factory for the VersionService.
 *
 * Wires the installed and available version sources with their dependencies
 * from the injector. Uses Packagist as the default available-version source.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class VersionServiceFactory
{
    /**
     * Create a fully-wired VersionService instance.
     *
     * @param Injector $injector Horde dependency injection container
     *
     * @return VersionService
     */
    public function create(Injector $injector): VersionService
    {
        $appService = $injector->getInstance(ApplicationService::class);
        $installed = new HordeYmlInstalledSource($appService);

        $available = $this->createAvailableSource($injector);

        return new VersionService($installed, $available);
    }

    /**
     * Create the available-version source.
     *
     * Default implementation uses PackagistAvailableSource. To swap in a
     * custom source (e.g. a horde.org endpoint), register an alternative
     * VersionServiceFactory or bind AvailableVersionSource directly.
     *
     * @param Injector $injector Horde dependency injection container
     *
     * @return AvailableVersionSource
     */
    private function createAvailableSource(Injector $injector): AvailableVersionSource
    {
        $httpClient = $injector->getInstance(ClientInterface::class);
        $requestFactory = $injector->getInstance(RequestFactoryInterface::class);

        try {
            $cache = $injector->getInstance(CacheInterface::class);
        } catch (Throwable) {
            $cache = new Cache(new NullStorage());
        }

        return new PackagistAvailableSource($httpClient, $requestFactory, $cache);
    }
}
