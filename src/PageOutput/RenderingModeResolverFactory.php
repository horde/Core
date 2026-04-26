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

use Horde\Browser\Browser;
use Horde\Core\Session\HordeSession;
use Horde\Injector\Injector;

class RenderingModeResolverFactory
{
    public function create(Injector $injector): RenderingModeResolver
    {
        return new RenderingModeResolver(
            $injector->getInstance(HordeSession::class),
            $injector->getInstance(Browser::class),
        );
    }
}
