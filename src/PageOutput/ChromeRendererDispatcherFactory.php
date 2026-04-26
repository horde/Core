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

use Horde\Injector\Injector;

class ChromeRendererDispatcherFactory
{
    public function create(Injector $injector): ChromeRendererDispatcher
    {
        return new ChromeRendererDispatcher(
            $injector->getInstance(DesktopChromeRenderer::class),
            $injector->getInstance(ResponsiveChromeRenderer::class),
        );
    }
}
