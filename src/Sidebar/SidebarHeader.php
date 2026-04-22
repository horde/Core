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
 * Immutable value object for a sidebar container header.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class SidebarHeader
{
    /**
     * @param string $id DOM ID for the header toggle element
     * @param string $label Header display text
     * @param bool $collapsed Whether the container starts collapsed
     * @param string|null $addUrl URL for the "add" action link
     * @param string|null $addLabel Tooltip text for the "add" action
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly bool $collapsed = false,
        public readonly ?string $addUrl = null,
        public readonly ?string $addLabel = null,
    ) {}
}
