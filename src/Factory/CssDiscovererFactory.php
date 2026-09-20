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
use Horde\Core\Assets\CascadeCssDiscoverer;
use Horde\Core\Assets\CssDiscoverer;
use Horde\Core\Assets\CssHookProvider;
use Horde\Core\Assets\TextDirectionProvider;
use Horde\Core\Path\PathBuilderInterface;
use Horde\Core\Uri\UriBuilderInterface;
use Horde\Injector\Injector;
use Throwable;

class CssDiscovererFactory
{
    public function create(Injector $injector): CssDiscoverer
    {
        $pathBuilder = $injector->get(PathBuilderInterface::class);
        $uriBuilder = $injector->get(UriBuilderInterface::class);
        $filesystem = $injector->get(AssetFilesystem::class);

        $textDirection = null;
        try {
            $textDirection = $injector->get(TextDirectionProvider::class);
        } catch (Throwable) {
        }

        $hookProvider = null;
        try {
            $hookProvider = $injector->get(CssHookProvider::class);
        } catch (Throwable) {
        }

        return new CascadeCssDiscoverer($pathBuilder, $uriBuilder, $filesystem, $textDirection, $hookProvider);
    }
}
