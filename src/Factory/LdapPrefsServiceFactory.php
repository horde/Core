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

use Horde\Core\Service\LdapPrefsService;
use Horde\Core\Service\HordeLdapService;
use Horde\Core\Config\ConfigLoader;
use Horde_Injector;

/**
 * Factory for creating LDAP-based PrefsService
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class LdapPrefsServiceFactory
{
    /**
     * Create LDAP prefs service from configuration
     *
     * @param Horde_Injector $injector Dependency injector
     * @return LdapPrefsService LDAP prefs service instance
     * @throws \RuntimeException If configuration invalid
     */
    public function create(Horde_Injector $injector): LdapPrefsService
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $config = $loader->load('horde');

        // Get LDAP service (may use service-specific connection)
        $ldapService = $injector->getInstance(HordeLdapService::class);

        // Get prefs configuration
        $params = $config->get('prefs.params', []);

        if (empty($params['basedn'])) {
            throw new \RuntimeException('LDAP prefs require basedn configuration');
        }

        return new LdapPrefsService(
            ldapService: $ldapService,
            basedn: $params['basedn']
        );
    }
}
