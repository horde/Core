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

use Horde\Core\Service\LdapGroupService;
use Horde\Core\Service\HordeLdapService;
use Horde\Core\Config\ConfigLoader;
use Horde_Injector;
use RuntimeException;

/**
 * Factory for creating LDAP-based GroupService
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class LdapGroupServiceFactory
{
    /**
     * Create LDAP group service from configuration
     *
     * @param Horde_Injector $injector Dependency injector
     * @return LdapGroupService LDAP group service instance
     * @throws RuntimeException If configuration invalid
     */
    public function create(Horde_Injector $injector): LdapGroupService
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $config = $loader->load('horde');

        // Get LDAP service (may use service-specific connection)
        $ldapService = $injector->getInstance(HordeLdapService::class);

        // Get group configuration
        $params = $config->get('groups.params', []);

        if (empty($params['basedn'])) {
            throw new RuntimeException('LDAP groups require basedn configuration');
        }

        return new LdapGroupService(
            ldapService: $ldapService,
            basedn: $params['basedn'],
            gidAttr: $params['gid'] ?? 'cn',
            memberAttr: $params['memberuid'] ?? 'memberUid',
            objectClass: $params['objectclass'] ?? ['posixGroup'],
            newGroupObjectClass: $params['newgroup_objectclass'] ?? ['posixGroup']
        );
    }
}
