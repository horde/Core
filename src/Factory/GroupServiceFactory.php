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

use Horde\Core\Service\GroupService;
use Horde\Core\Service\SqlGroupService;
use Horde_Injector;

/**
 * Factory for creating GroupService instances
 *
 * Creates the appropriate GroupService implementation based on the
 * configured group driver (SQL, LDAP, etc.).
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class GroupServiceFactory
{
    /**
     * Create GroupService instance
     *
     * @param Horde_Injector $injector Dependency injector
     * @return GroupService Group service instance
     */
    public function create(Horde_Injector $injector): GroupService
    {
        // Get the legacy Horde_Group instance (already configured via Horde_Core_Factory_Group)
        $groupBackend = $injector->getInstance('Horde_Group');

        // Wrap in modern service
        return new SqlGroupService($groupBackend);
    }
}
