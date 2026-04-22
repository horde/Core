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

use Horde\Core\Horde;
use Horde\Core\Service\PermissionService;
use Horde\Core\Service\PrefsService;
use Horde\Injector\Attribute\Factory;
use Horde_Registry;
use Exception;

/**
 * Sidebar panel showing admin navigation entries.
 *
 * Reads the admin_list from the registry, filters by permission,
 * and builds a single collapsible container with tree rows.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: AdminSidebarPanelFactory::class, method: 'create')]
class AdminSidebarPanel implements SidebarPanel
{
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly PermissionService $permissions,
        private readonly PrefsService $prefs,
    ) {}

    /**
     * @return SidebarContainer[]
     */
    public function getContainers(string $currentUrl): array
    {
        $adminList = $this->loadAdminList();
        if ($adminList === []) {
            return [];
        }

        $isAdmin = false;
        $uid = '';
        try {
            $isAdmin = $this->registry->isAdmin();
            $uid = $this->registry->getAuth() ?: '';
        } catch (Exception) {
        }

        $rows = [];
        foreach ($adminList as $method => $val) {
            if (!$this->hasAccess($method, $isAdmin, $uid)) {
                continue;
            }

            $label = Horde::stripAccessKey($val['name']);
            $url = $this->registry->applicationWebPath($val['link'], 'horde');
            $selected = $this->isSelected($url, $currentUrl);

            $escapedUrl = htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $escapedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $rows[] = new SidebarRow(
                label: $label,
                url: (string) $url,
                selected: $selected,
                cssClass: 'horde-admin-' . ($val['icon'] ?? ''),
                linkHtml: '<a href="' . $escapedUrl . '">' . $escapedLabel . '</a>',
            );
        }

        if ($rows === []) {
            return [];
        }

        return [
            new SidebarContainer(
                id: 'admin',
                header: new SidebarHeader(
                    id: 'horde-toggle-admin',
                    label: _("Administration"),
                    collapsed: false,
                ),
                rows: $rows,
                type: 'tree',
            ),
        ];
    }

    public function buildSidebarData(string $currentUrl): SidebarData
    {
        $uid = '';
        try {
            $uid = $this->registry->getAuth() ?: '';
        } catch (Exception) {
        }

        $width = 150;
        if ($uid !== '') {
            $widthVal = $this->prefs->getValue($uid, 'horde', 'sidebar_width');
            if ($widthVal !== null && (int) $widthVal > 0) {
                $width = (int) $widthVal;
            }
        }

        $isRtl = false;
        try {
            $isRtl = (bool) ($this->registry->nlsconfig->curr_rtl ?? false);
        } catch (Exception) {
        }

        return new SidebarData(
            containers: $this->getContainers($currentUrl),
            width: $width,
            isRtl: $isRtl,
        );
    }

    /**
     * @return array<string, array{link: string, name: string, icon: string}>
     */
    private function loadAdminList(): array
    {
        try {
            return $this->registry->callByPackage('horde', 'admin_list');
        } catch (Exception) {
            return [];
        }
    }

    private function hasAccess(string $method, bool $isAdmin, string $uid): bool
    {
        if ($isAdmin) {
            return true;
        }

        if ($uid === '') {
            return false;
        }

        $permName = 'horde:administration:' . $method;
        try {
            return $this->permissions->exists($permName)
                && $this->permissions->hasPermission($permName, $uid, ['show']);
        } catch (Exception) {
            return false;
        }
    }

    private function isSelected(string|object $entryUrl, string $currentUrl): bool
    {
        $entry = rtrim((string) $entryUrl, '/');
        $current = rtrim($currentUrl, '/');

        if ($entry === '' || $current === '') {
            return false;
        }

        return str_starts_with($current, $entry);
    }
}
