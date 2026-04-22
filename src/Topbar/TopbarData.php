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

/**
 * Immutable data object for the traditional desktop topbar.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class TopbarData
{
    /**
     * @param string $portalUrl URL to the portal page
     * @param string $version Version string displayed in topbar
     * @param TopbarMenuNode[] $menuTree Root nodes of the navigation menu
     * @param TopbarSearchConfig|null $searchConfig Search form configuration
     * @param string|null $logoutUrl Logout URL (null if not authenticated)
     * @param string|null $loginUrl Login URL (null if already authenticated)
     * @param string $date Formatted current date
     * @param bool $sidebarEnabled Whether sidebar is active for this page
     * @param int $sidebarWidth Sidebar width in pixels
     * @param array $jsConfig Values for HordeTopbar.conf JS variable
     * @param string|null $subinfo Right-aligned subbar content
     */
    public function __construct(
        public readonly string $portalUrl,
        public readonly string $version,
        public readonly array $menuTree = [],
        public readonly ?TopbarSearchConfig $searchConfig = null,
        public readonly ?string $logoutUrl = null,
        public readonly ?string $loginUrl = null,
        public readonly string $date = '',
        public readonly bool $sidebarEnabled = true,
        public readonly int $sidebarWidth = 150,
        public readonly array $jsConfig = [],
        public readonly ?string $subinfo = null,
    ) {}
}
