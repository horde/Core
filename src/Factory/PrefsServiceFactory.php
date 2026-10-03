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
use Horde\Core\Config\PrefsConfigLoader;
use Horde\Core\Service\HordeDbService;
use Horde\Core\Service\HordeLdapService;
use Horde\Core\Service\LdapPrefsService;
use Horde\Core\Service\NullPrefsService;
use Horde\Core\Service\PrefsService;
use Horde\Core\Service\SqlPrefsService;
use Horde\Injector\Injector;
use RuntimeException;

/**
 * Factory for PrefsService from configuration
 *
 * Reads $conf['prefs']['driver'] from horde's conf.php and constructs the
 * appropriate PrefsService backend.
 *
 * Supported drivers:
 * - 'sql'          SQL storage via Horde_Prefs_Storage_Sql (default)
 * - 'ldap'         LDAP attribute storage via LdapPrefsService
 * - 'null'         In-memory only, no persistence
 * - 'session'      Alias for 'null'
 *
 * Driver-specific conf.php keys (all under $conf['prefs']['params']):
 *
 * sql:
 *   table   - table name (default: 'horde_prefs')
 *
 * ldap:
 *   basedn  - Base DN for user searches; required
 *             e.g. 'ou=people,dc=example,dc=com'
 *   The LDAP connection itself is sourced from HordeLdapService (configured
 *   separately under $conf['ldap'] or $conf['auth']['params']).
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
     * @param Injector $injector Dependency injector
     * @return PrefsService Prefs service instance
     * @throws RuntimeException If driver unsupported
     */
    public function create(Injector $injector): PrefsService
    {
        $loader = $injector->get(ConfigLoader::class);
        $state = $loader->load('horde');

        $driver = $state->get('prefs.driver', 'sql');
        $params = $state->get('prefs.params', []);

        return match (strtolower($driver)) {
            'sql'           => $this->createSqlBackend($injector, $params),
            'ldap'          => $this->createLdapBackend($injector, $params),
            'null', 'session' => $this->createNullBackend(),
            default         => throw new RuntimeException("Unsupported prefs driver: $driver"),
        };
    }

    /**
     * Create SQL prefs backend
     *
     * @param Injector $injector Dependency injector
     * @param array $params Prefs configuration parameters
     * @return SqlPrefsService SQL prefs service
     */
    private function createSqlBackend(Injector $injector, array $params): SqlPrefsService
    {
        // Get DB service (supports 'horde:prefs' pattern in future)
        $dbService = $injector->get(HordeDbService::class);

        $table = $params['table'] ?? 'horde_prefs';

        return new SqlPrefsService(
            $dbService->getAdapter(),
            $injector->get(PrefsConfigLoader::class),
            $table
        );
    }

    /**
     * Create LDAP prefs backend
     *
     * Requires $conf['prefs']['params']['basedn'] in conf.php.
     * The LDAP connection is resolved from the container via HordeLdapService,
     * configured under $conf['ldap'] (or $conf['auth']['params'] for auth-bound
     * connections). See doc/PREFERENCES.md for details.
     *
     * @param Injector $injector Dependency injector
     * @param array    $params   Prefs configuration parameters
     * @return LdapPrefsService LDAP prefs service
     * @throws RuntimeException If basedn is missing from configuration
     */
    private function createLdapBackend(Injector $injector, array $params): LdapPrefsService
    {
        if (empty($params['basedn'])) {
            throw new RuntimeException(
                "LDAP prefs require \$conf['prefs']['params']['basedn'] in conf.php"
            );
        }

        return new LdapPrefsService(
            ldapService: $injector->get(HordeLdapService::class),
            prefsConfigLoader: $injector->get(PrefsConfigLoader::class),
            basedn: $params['basedn']
        );
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
