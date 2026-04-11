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
 * Standard database service implementation for Horde
 *
 * Simple wrapper around Horde_Db_Adapter to provide
 * service interface for dependency injection.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class StandardHordeDbService implements HordeDbService
{
    /**
     * Constructor
     *
     * @param Horde_Db_Adapter $adapter Database adapter instance
     */
    public function __construct(
        private Horde_Db_Adapter $adapter
    ) {}

    /**
     * Get database adapter
     *
     * @return Horde_Db_Adapter Database adapter instance
     */
    public function getAdapter(): Horde_Db_Adapter
    {
        return $this->adapter;
    }
}
