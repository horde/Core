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

use Horde_Db_Adapter;

/**
 * Database service interface for Horde
 *
 * Provides access to Horde's database adapter. Future versions
 * may support multi-app database connections.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface HordeDbService
{
    /**
     * Get database adapter
     *
     * @return Horde_Db_Adapter Database adapter instance
     */
    public function getAdapter(): Horde_Db_Adapter;
}
