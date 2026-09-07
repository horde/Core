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
use Horde\Core\Config\ConfigLoader;
use Horde\Injector\Injector;
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
     * @param Injector $injector Dependency injector
     * @return LdapGroupService LDAP group service instance
     * @throws RuntimeException If configuration invalid
     */
    public function create(Injector $injector): LdapGroupService
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $config = $loader->load('horde');

        // Resolve the LDAP connection for the 'groups' service specifically:
        // falls back to the default 'ldap' config if 'ldap.service.groups'
        // isn't set (see HordeLdapServiceFactory::resolveConfig()).
        $ldapFactory = $injector->getInstance(HordeLdapServiceFactory::class);
        $ldapService = $ldapFactory->create($injector, 'horde:groups');

        // Get group configuration (legacy conf.php key is singular: 'group', not 'groups')
        $params = $config->get('group.params', []);

        if (empty($params['basedn'])) {
            throw new RuntimeException('LDAP groups require basedn configuration');
        }

        return new LdapGroupService(
            ldapService: $ldapService,
            basedn: $params['basedn'],
            gidAttr: $params['gid'] ?? 'cn',
            memberAttr: $params['memberuid'] ?? 'memberUid',
            search: $params['search'] ?? ['objectclass' => ['posixGroup']],
            newGroupObjectClass: $params['newgroup_objectclass'] ?? ['posixGroup']
        );
    }
}
