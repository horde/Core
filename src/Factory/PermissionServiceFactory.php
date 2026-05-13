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

use Horde\Core\Service\PermissionService;
use Horde\Core\Service\SqlPermissionService;
use Horde\Core\Service\NullPermissionService;
use Horde\Core\Service\GroupService;
use Horde\Core\Config\ConfigLoader;
use Horde_Perms_Sql;
use Horde_Perms_Null;
use Horde\Injector\Injector;
use Horde_Cache;
use Horde_Log_Logger;
use RuntimeException;

/**
 * Factory for creating PermissionService with proper backend
 *
 * Creates permission service from configuration, supporting:
 * - SQL backend (with service-specific or shared DB connection)
 * - Null backend (permissions disabled)
 *
 * Handles connection pooling via DbServiceFactory when using SQL backend.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class PermissionServiceFactory
{
    /**
     * Create PermissionService instance
     *
     * @param Injector $injector Dependency injector
     * @return PermissionService Permission service with configured backend
     * @throws RuntimeException If driver unsupported
     */
    public function create(Injector $injector): PermissionService
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        $driver = strtolower($state->get('perms.driver', 'null'));
        $params = $state->get('perms.params', []);

        return match ($driver) {
            'sql' => $this->createSqlBackend($injector, $params),
            'null' => new NullPermissionService(),
            default => throw new RuntimeException("Unsupported perms driver: {$driver}"),
        };
    }

    /**
     * Create SQL permission backend
     *
     * Uses DbServiceFactory for connection pooling - permissions can use:
     * - System-wide horde connection (driverconfig='horde')
     * - Service-specific connection (custom DB params)
     *
     * @param Injector $injector Dependency injector
     * @param array $params Permission configuration parameters
     * @return SqlPermissionService SQL permission service
     */
    private function createSqlBackend(
        Injector $injector,
        array $params
    ): SqlPermissionService {
        // Get database connection via DbServiceFactory with pooling
        $dbFactory = $injector->getInstance(DbServiceFactory::class);
        $dbService = $dbFactory->create($injector, 'horde:perms');

        // Get dependencies
        $cache = $injector->getInstance(Horde_Cache::class);
        $logger = $injector->getInstance(Horde_Log_Logger::class);
        $groupService = $injector->getInstance(GroupService::class);

        // Create legacy Horde_Perms_Sql backend
        $permsParams = [
            'db' => $dbService->getAdapter(),
            'table' => $params['table'] ?? 'horde_perms',
            'cache' => $cache,
            'logger' => $logger,
        ];

        $backend = new Horde_Perms_Sql($permsParams);

        return new SqlPermissionService($backend, $groupService);
    }
}
