<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Factory;

use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\Driver\HashTable\MemcacheDriver;
use Horde\Core\Config\Driver\HashTable\RedisDriver;
use Horde\Core\Config\Driver\Ldap\LdapDriver;
use Horde\Core\Config\Driver\NoSql\MongoDBDriver;
use Horde\Core\Config\Driver\Sql\MSSQLDriver;
use Horde\Core\Config\Driver\Sql\MySQLDriver;
use Horde\Core\Config\Driver\Sql\OracleDriver;
use Horde\Core\Config\Driver\Sql\PostgreSQLDriver;
use Horde\Core\Config\Driver\Sql\SQLiteDriver;
use Horde_Injector;

/**
 * Factory for DriverRepository.
 *
 * Bootstraps the driver repository with all available drivers.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class DriverRepositoryFactory
{
    public function __construct(
        private readonly Horde_Injector $injector,
    ) {}

    /**
     * Create and populate DriverRepository.
     */
    public function create(): DriverRepository
    {
        $repository = new DriverRepository();

        // Register built-in SQL drivers
        $repository->register(new MySQLDriver());
        $repository->register(new PostgreSQLDriver());
        $repository->register(new SQLiteDriver());
        $repository->register(new OracleDriver());
        $repository->register(new MSSQLDriver());

        // Register LDAP driver
        $repository->register(new LdapDriver());

        // Register NoSQL drivers (document stores)
        $repository->register(new MongoDBDriver());

        // Register HashTable drivers (distributed caching)
        $repository->register(new MemcacheDriver());
        $repository->register(new RedisDriver());

        // Future: Auto-discover drivers from applications
        // Future: Allow apps to register custom drivers via hooks

        return $repository;
    }
}
