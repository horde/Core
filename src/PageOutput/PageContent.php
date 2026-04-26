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

use Horde\Core\Sidebar\SidebarData;

final class PageContent
{
    /**
     * @param string $title Page title
     * @param string $bodyHtml Rendered body content (no chrome)
     * @param string $app Application name for asset resolution
     * @param ?SidebarData $sidebarData Sidebar data (desktop only; ignored in responsive)
     * @param string[] $cssFiles Extra CSS files to include
     * @param string[] $jsFiles Extra JS files to include
     */
    public function __construct(
        public readonly string $title,
        public readonly string $bodyHtml,
        public readonly string $app = 'horde',
        public readonly ?SidebarData $sidebarData = null,
        public readonly array $cssFiles = [],
        public readonly array $jsFiles = [],
    ) {}
}
