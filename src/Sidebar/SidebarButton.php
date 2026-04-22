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
 * Immutable value object for the sidebar "New" action button.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class SidebarButton
{
    /**
     * @param string $label Button text with access key markup (HTML)
     * @param string $url Opening <a> tag HTML
     * @param string|null $extra HTML for split button extra action
     */
    public function __construct(
        public readonly string $label,
        public readonly string $url,
        public readonly ?string $extra = null,
    ) {}
}
