<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Factory;

use Horde\Injector\Injector;
use Horde_Registry;

class HordeRegistryFactory
{
    public function create(Injector $injector): Horde_Registry
    {
        $GLOBALS['injector'] ??= $injector;

        return new Horde_Registry();
    }
}
