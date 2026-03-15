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

namespace Horde\Core\Service;

use Horde_Ldap;

/**
 * LDAP service interface for Horde
 *
 * Provides access to Horde's LDAP connections. Implementations
 * support multi-app and service-specific LDAP connections.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface HordeLdapService
{
    /**
     * Get LDAP connection
     *
     * @return Horde_Ldap LDAP connection instance
     */
    public function getAdapter(): Horde_Ldap;
}
