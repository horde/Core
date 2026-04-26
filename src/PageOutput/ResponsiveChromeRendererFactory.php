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

namespace Horde\Core\PageOutput;

use Horde\Core\Assets\CssDiscoverer;
use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\View\ResponsiveTopbar;
use Horde\Injector\Injector;
use Horde_Registry;

class ResponsiveChromeRendererFactory
{
    public function create(Injector $injector): ResponsiveChromeRenderer
    {
        $registry = $injector->getInstance(Horde_Registry::class);

        $topbarFactory = static function (string $app) use ($registry): string {
            $topbar = new ResponsiveTopbar($registry, $app);
            return $topbar->render();
        };

        return new ResponsiveChromeRenderer(
            $injector->getInstance(CssDiscoverer::class),
            $injector->getInstance(JsDiscoverer::class),
            $topbarFactory,
        );
    }
}
