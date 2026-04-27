<?php

declare(strict_types=1);

namespace Horde\Core\Factory;

use Horde\Core\Api\ApiRegistry;
use Horde_Injector;

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */
class ApiRegistryFactory
{
    public function create(Horde_Injector $injector): ApiRegistry
    {
        return new ApiRegistry();
    }
}
