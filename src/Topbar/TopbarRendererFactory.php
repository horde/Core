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

namespace Horde\Core\Topbar;

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Injector\Injector;

class TopbarRendererFactory
{
    public function create(Injector $injector): TopbarRenderer
    {
        return new TopbarRenderer(
            $injector->getInstance(AssetCollector::class),
            $injector->getInstance(JsDiscoverer::class),
        );
    }
}
