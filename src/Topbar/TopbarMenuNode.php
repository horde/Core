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

use Stringable;

/**
 * Single node in the topbar menu tree.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class TopbarMenuNode
{
    /**
     * @param string $id Node identifier
     * @param string $label Display label
     * @param string|Stringable|null $url Link target URL
     * @param string|null $iconClass CSS class for icon
     * @param string|null $target Link target attribute
     * @param string|null $onclick JavaScript onclick handler
     * @param bool $active Whether this node is the current page
     * @param TopbarMenuNode[] $children Child nodes
     * @param bool $noarrow Suppress dropdown arrow indicator
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string|Stringable|null $url = null,
        public readonly ?string $iconClass = null,
        public readonly ?string $target = null,
        public readonly ?string $onclick = null,
        public readonly bool $active = false,
        public readonly array $children = [],
        public readonly bool $noarrow = false,
    ) {}
}
