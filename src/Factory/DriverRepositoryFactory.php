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

use Horde\Core\Config\Driver\Alarms\NullAlarmsDriver;
use Horde\Core\Config\Driver\Alarms\SqlAlarmsDriver;
use Horde\Core\Config\Driver\Auth\ApplicationAuthDriver;
use Horde\Core\Config\Driver\Auth\AutoAuthDriver;
use Horde\Core\Config\Driver\Auth\FtpAuthDriver;
use Horde\Core\Config\Driver\Auth\HttpAuthDriver;
use Horde\Core\Config\Driver\Auth\HttpRemoteAuthDriver;
use Horde\Core\Config\Driver\Auth\ImapAuthDriver;
use Horde\Core\Config\Driver\Auth\LdapAuthDriver;
use Horde\Core\Config\Driver\Auth\PamAuthDriver;
use Horde\Core\Config\Driver\Auth\RadiusAuthDriver;
use Horde\Core\Config\Driver\Auth\ShibbolethAuthDriver;
use Horde\Core\Config\Driver\Auth\SqlAuthDriver;
use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\Driver\Group\LdapGroupDriver;
use Horde\Core\Config\Driver\Group\MockGroupDriver;
use Horde\Core\Config\Driver\Group\SqlGroupDriver;
use Horde\Core\Config\Driver\HashTable\MemcacheDriver;
use Horde\Core\Config\Driver\HashTable\RedisDriver;
use Horde\Core\Config\Driver\Ldap\LdapDriver;
use Horde\Core\Config\Driver\NoSql\MongoDBDriver;
use Horde\Core\Config\Driver\Perms\NullPermsDriver;
use Horde\Core\Config\Driver\Perms\SqlPermsDriver;
use Horde\Core\Config\Driver\Prefs\FilePrefsDriver;
use Horde\Core\Config\Driver\Prefs\LdapPrefsDriver;
use Horde\Core\Config\Driver\Prefs\NoSqlPrefsDriver;
use Horde\Core\Config\Driver\Prefs\SessionPrefsDriver;
use Horde\Core\Config\Driver\Prefs\SqlPrefsDriver;
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

        // Register Auth drivers (authentication backends)
        $repository->register(new SqlAuthDriver());
        $repository->register(new LdapAuthDriver());
        $repository->register(new ImapAuthDriver());
        $repository->register(new RadiusAuthDriver());
        $repository->register(new ShibbolethAuthDriver());
        $repository->register(new FtpAuthDriver());
        $repository->register(new HttpAuthDriver());
        $repository->register(new HttpRemoteAuthDriver());
        $repository->register(new PamAuthDriver());
        $repository->register(new ApplicationAuthDriver());
        $repository->register(new AutoAuthDriver());

        // Register Prefs drivers (preference storage)
        $repository->register(new SqlPrefsDriver());
        $repository->register(new NoSqlPrefsDriver());
        $repository->register(new LdapPrefsDriver());
        $repository->register(new FilePrefsDriver());
        $repository->register(new SessionPrefsDriver());

        // Register Group drivers (group management)
        $repository->register(new SqlGroupDriver());
        $repository->register(new LdapGroupDriver());
        $repository->register(new MockGroupDriver());

        // Register Perms drivers (permissions)
        $repository->register(new SqlPermsDriver());
        $repository->register(new NullPermsDriver());

        // Register Alarms drivers (alarm storage)
        $repository->register(new SqlAlarmsDriver());
        $repository->register(new NullAlarmsDriver());

        // Future: Auto-discover drivers from applications
        // Future: Allow apps to register custom drivers via hooks

        return $repository;
    }
}
