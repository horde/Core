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

use Horde\Core\Assets\AssetFilesystem;
use Horde\Core\Assets\CascadeGraphicDiscoverer;
use Horde\Core\Assets\GraphicDiscoverer;
use Horde\Core\Path\PathBuilderInterface;
use Horde\Core\Uri\UriBuilderInterface;
use Horde\Injector\Injector;

class GraphicDiscovererFactory
{
    public function create(Injector $injector): GraphicDiscoverer
    {
        $pathBuilder = $injector->getInstance(PathBuilderInterface::class);
        $uriBuilder = $injector->getInstance(UriBuilderInterface::class);
        $filesystem = $injector->getInstance(AssetFilesystem::class);

        return new CascadeGraphicDiscoverer($pathBuilder, $uriBuilder, $filesystem);
    }
}
