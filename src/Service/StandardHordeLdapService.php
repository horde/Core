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
 * Standard LDAP service implementation for Horde
 *
 * Simple wrapper around Horde_Ldap to provide
 * service interface for dependency injection.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class StandardHordeLdapService implements HordeLdapService
{
    /**
     * Constructor
     *
     * @param Horde_Ldap $adapter LDAP adapter instance
     */
    public function __construct(
        private Horde_Ldap $adapter
    ) {}

    /**
     * Get LDAP connection
     *
     * @return Horde_Ldap LDAP connection instance
     */
    public function getAdapter(): Horde_Ldap
    {
        return $this->adapter;
    }
}
