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

namespace Horde\Core\Sidebar;

/**
 * Interface for reusable sidebar content panels.
 *
 * Controllers opt into sidebar content by injecting panels and
 * composing a SidebarData from their containers. This avoids global
 * hooks and makes the sidebar content explicit per controller.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
interface SidebarPanel
{
    /**
     * @param string $currentUrl Current request URL path for active-row highlighting
     * @return SidebarContainer[]
     */
    public function getContainers(string $currentUrl): array;
}
