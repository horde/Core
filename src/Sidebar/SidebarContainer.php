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
 * Immutable value object for a sidebar container section.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class SidebarContainer
{
    /**
     * @param string|null $id Container DOM ID
     * @param SidebarHeader|null $header Section header with collapse toggle
     * @param SidebarRow[] $rows Sidebar rows in this container
     * @param string $type Row type: 'tree', 'checkbox', or 'radiobox'
     * @param string|null $content Raw HTML alternative to rows
     */
    public function __construct(
        public readonly ?string $id = null,
        public readonly ?SidebarHeader $header = null,
        public readonly array $rows = [],
        public readonly string $type = 'tree',
        public readonly ?string $content = null,
    ) {}
}
