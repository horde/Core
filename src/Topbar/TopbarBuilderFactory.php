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

use Horde\Core\Service\PermissionService;
use Horde\Core\Service\PrefsService;
use Horde\Core\Session\HordeSession;
use Horde_Injector;
use Horde_Registry;

/**
 * Factory for TopbarBuilder.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class TopbarBuilderFactory
{
    public function create(Horde_Injector $injector): TopbarBuilder
    {
        return new TopbarBuilder(
            $injector->getInstance(Horde_Registry::class),
            $injector->getInstance(PrefsService::class),
            $injector->getInstance(PermissionService::class),
            $injector->getInstance(HordeSession::class),
        );
    }
}
