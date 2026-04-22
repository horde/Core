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
 * Immutable data object for the traditional desktop sidebar.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class SidebarData
{
    /**
     * @param SidebarButton|null $newButton Primary "New" action button
     * @param SidebarContainer[] $containers Sidebar sections
     * @param int $width Sidebar width in pixels
     * @param bool $isRtl Right-to-left layout
     * @param string|null $content Raw HTML fallback (used instead of containers)
     */
    public function __construct(
        public readonly ?SidebarButton $newButton = null,
        public readonly array $containers = [],
        public readonly int $width = 150,
        public readonly bool $isRtl = false,
        public readonly ?string $content = null,
    ) {}
}
