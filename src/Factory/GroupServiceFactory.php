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

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Service\GroupService;
use Horde\Core\Service\SqlGroupService;
use Horde\Injector\Injector;
use Horde_Group_Sql;
use RuntimeException;

/**
 * Factory for creating GroupService instances.
 *
 * Reads driver and params from the modern ConfigLoader rather than
 * routing through the legacy Horde_Core_Factory_Group. The legacy
 * factory depends on the `$conf` global being populated by
 * Horde_Registry::appInit(), which the modern rampage-based request
 * stack does not run. Modern requests (admin API) that need groups
 * would fail with an obscure "array expected, null given" from
 * Horde::getDriverConfig() otherwise.
 *
 * Mirrors PermissionServiceFactory's shape: modern config source,
 * DbServiceFactory for pooled DB connections, direct construction
 * of the legacy Horde_Group_* backend from typed config values, and
 * a match statement per driver.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class GroupServiceFactory
{
    /**
     * Create a GroupService instance.
     *
     * @param Injector $injector Dependency injector
     * @return GroupService Group service with configured backend
     * @throws RuntimeException If the configured driver is unsupported
     */
    public function create(Injector $injector): GroupService
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        $driver = strtolower($state->get('group.driver', 'sql'));
        $params = $state->get('group.params', []);

        return match ($driver) {
            'sql' => $this->createSqlBackend($injector, $params),
            'ldap' => $injector->getInstance(LdapGroupServiceFactory::class)->create($injector),
            default => throw new RuntimeException(
                "Unsupported group driver: {$driver}. "
                . "Modern GroupService currently supports 'sql' and 'ldap'. "
                . "File and other legacy drivers are not yet ported."
            ),
        };
    }

    /**
     * Construct the SQL-backed group service.
     *
     * Uses DbServiceFactory for connection pooling. The connection
     * shares the main horde pool when params.driverconfig === 'horde'
     * (the common case); an explicit db config in params yields a
     * separate pool.
     *
     * Horde_Group_Sql only requires 'db' in its params. The base
     * class treats 'cache' as optional (defaults to a Horde_Support_Stub)
     * and never touches a logger. Deliberately omitting both here keeps
     * this factory free of any legacy Horde_Core_Factory_* dependency
     * that would need $GLOBALS['conf'] populated — the modern rampage
     * request stack doesn't do that.
     *
     * @param Injector $injector Dependency injector
     * @param array $params Group driver params from conf.php
     * @return SqlGroupService SQL group service wrapping Horde_Group_Sql
     */
    private function createSqlBackend(
        Injector $injector,
        array $params,
    ): SqlGroupService {
        $dbFactory = $injector->getInstance(DbServiceFactory::class);
        $dbService = $dbFactory->create($injector, 'horde:group');

        $backend = new Horde_Group_Sql([
            'db' => $dbService->getAdapter(),
        ]);

        return new SqlGroupService($backend);
    }
}
