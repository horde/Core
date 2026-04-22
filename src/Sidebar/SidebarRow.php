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
 * Immutable value object for a single sidebar row.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class SidebarRow
{
    /**
     * @param string $label Display text
     * @param string $url Link target
     * @param bool $selected Whether this row is active
     * @param string $type Row type: 'tree', 'checkbox', or 'radiobox'
     * @param string|null $cssClass CSS icon class (tree type)
     * @param string|null $id DOM ID for the row link
     * @param string|null $color Background color (checkbox/radiobox)
     * @param string|null $foregroundColor Calculated text color
     * @param string|null $editUrl URL for edit action
     * @param string|null $onclick JavaScript onclick handler
     * @param string|null $target Link target attribute
     * @param string $linkHtml Pre-built link HTML
     * @param string|null $editLinkHtml Pre-built edit link HTML
     * @param string|null $style Inline CSS styles
     */
    public function __construct(
        public readonly string $label,
        public readonly string $url = '',
        public readonly bool $selected = false,
        public readonly string $type = 'tree',
        public readonly ?string $cssClass = null,
        public readonly ?string $id = null,
        public readonly ?string $color = null,
        public readonly ?string $foregroundColor = null,
        public readonly ?string $editUrl = null,
        public readonly ?string $onclick = null,
        public readonly ?string $target = null,
        public readonly string $linkHtml = '',
        public readonly ?string $editLinkHtml = null,
        public readonly ?string $style = null,
    ) {}
}
