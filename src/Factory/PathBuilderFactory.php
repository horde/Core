<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Factory;

use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Path\PathBuilder;
use Horde\Core\Path\PathBuilderInterface;
use Horde\Injector\Injector;

class PathBuilderFactory
{
    public function create(Injector $injector): PathBuilderInterface
    {
        $registryLoader = $injector->getInstance(RegistryConfigLoader::class);
        $registryState = $registryLoader->load();

        return new PathBuilder($registryState);
    }
}
