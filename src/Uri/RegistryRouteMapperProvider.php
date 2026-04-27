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
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Uri;

use Horde\Routes\Mapper;

class RegistryRouteMapperProvider implements RouteMapperProvider
{
    /** @var array<string, Mapper|null> */
    private array $cache = [];

    public function __construct(
        private readonly \Horde\Core\Config\RegistryConfigLoader $registryLoader,
    ) {}

    public function getMapper(string $app): ?Mapper
    {
        if (array_key_exists($app, $this->cache)) {
            return $this->cache[$app];
        }

        $state = $this->registryLoader->load();
        $appConfig = $state->getApplication($app);
        if ($appConfig === null) {
            $this->cache[$app] = null;
            return null;
        }

        $fileroot = $appConfig['fileroot'] ?? null;
        if ($fileroot === null || !is_dir($fileroot)) {
            $this->cache[$app] = null;
            return null;
        }

        $routeFile = $fileroot . '/config/routes.php';
        if (!file_exists($routeFile)) {
            $this->cache[$app] = null;
            return null;
        }

        $mapper = new Mapper();
        $webroot = $appConfig['webroot'] ?? '/' . $app;
        $mapper->prefix = $webroot;

        include $routeFile;

        $localRouteFile = $fileroot . '/config/routes.local.php';
        if (file_exists($localRouteFile)) {
            include $localRouteFile;
        }

        $this->cache[$app] = $mapper;
        return $mapper;
    }
}
