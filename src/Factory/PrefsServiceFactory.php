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
use Horde\Core\Service\PrefsService;
use Horde\Core\Service\SqlPrefsService;
use Horde\Core\Service\NullPrefsService;
use Horde\Core\Service\HordeDbService;
use Horde_Injector;
use RuntimeException;

/**
 * Factory for PrefsService from configuration
 *
 * Creates prefs storage backend based on conf.php settings.
 * Supports SQL initially, designed for expansion.
 *
 * Currently implemented:
 * - 'sql': SQL storage (Horde_Prefs_Storage_Sql)
 * - 'null', 'session': Null storage (in-memory only)
 *
 * TODO: Implement additional backends as needed:
 *
 * LDAP Backend:
 * - Driver: 'ldap'
 * - Requires: LdapService (similar to DbServiceFactory pattern)
 * - Config: $conf['prefs']['params'] with:
 *   - hostspec: LDAP server hostname
 *   - port: LDAP port (default 389)
 *   - basedn: Base DN for prefs (e.g., 'ou=prefs,dc=example,dc=com')
 *   - uid: Attribute for username (default 'uid')
 *   - binddn: Bind DN for authentication
 *   - bindpw: Bind password
 * - Notes: LDAP has limited attribute storage, less common for prefs
 * - Implementation: Create LdapServiceFactory, then Horde_Prefs_Storage_Ldap wrapper
 *
 * NoSQL Backend (MongoDB):
 * - Driver: 'nosql' or 'mongo'
 * - Requires: NoSQL service factory
 * - Config: $conf['nosql'] with connection params
 * - Notes: Check $conf['prefs']['driver'] == 'nosql', detect backend type (mongo/redis)
 * - Implementation: Create NoSqlServiceFactory, wrap Horde_Prefs_Storage_Mongo
 *
 * File Backend:
 * - Driver: 'file'
 * - Requires: File path configuration only
 * - Config: $conf['prefs']['params']['directory'] - Path to store prefs files
 * - Notes: Simple, no service dependencies, but slow at scale
 * - Implementation: Wrap Horde_Prefs_Storage_File with directory param
 *
 * Kolab IMAP Backend:
 * - Driver: 'kolab_imap'
 * - Requires: Kolab session/storage service
 * - Config: Uses authenticated user's IMAP connection
 * - Notes: Unavailable for admin users, stores prefs in IMAP folders
 * - Implementation: Create KolabServiceFactory, wrap Horde_Prefs_Storage_KolabImap
 *
 * App:Service Pattern:
 * - Factory supports 'app:service' notation for DB connections
 * - Example: $conf['prefs']['params']['driverconfig'] = 'horde:prefs'
 * - Gets DB connection specific to that service: DbServiceFactory->create('horde', 'prefs')
 * - For now, we use default 'horde' SQL connection
 * - Future: Extend DbServiceFactory to accept optional service parameter
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class PrefsServiceFactory
{
    /**
     * Create PrefsService instance
     *
     * @param Horde_Injector $injector Dependency injector
     * @return PrefsService Prefs service instance
     * @throws RuntimeException If driver unsupported
     */
    public function create(Horde_Injector $injector): PrefsService
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        $driver = $state->get('prefs.driver', 'sql');
        $params = $state->get('prefs.params', []);

        return match (strtolower($driver)) {
            'sql' => $this->createSqlBackend($injector, $params),
            'null', 'session' => $this->createNullBackend(),
            default => throw new RuntimeException("Unsupported prefs driver: $driver (TODO: implement)"),
        };
    }

    /**
     * Create SQL prefs backend
     *
     * @param Horde_Injector $injector Dependency injector
     * @param array $params Prefs configuration parameters
     * @return SqlPrefsService SQL prefs service
     */
    private function createSqlBackend(Horde_Injector $injector, array $params): SqlPrefsService
    {
        // Get DB service (supports 'horde:prefs' pattern in future)
        $dbService = $injector->getInstance(HordeDbService::class);

        $table = $params['table'] ?? 'horde_prefs';

        return new SqlPrefsService($dbService->getAdapter(), $table);
    }

    /**
     * Create null prefs backend
     *
     * In-memory only, for testing or session-based prefs.
     *
     * @return NullPrefsService Null prefs service
     */
    private function createNullBackend(): NullPrefsService
    {
        return new NullPrefsService();
    }
}
