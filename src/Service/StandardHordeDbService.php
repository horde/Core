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

use Horde\Db\Adapter;

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
     * @param Adapter $adapter Database adapter instance
     */
    public function __construct(
        private Adapter $adapter
    ) {}

    /**
     * Get database adapter
     *
     * @return Adapter Database adapter instance
     */
    public function getAdapter(): Adapter
    {
        return $this->adapter;
    }
}
