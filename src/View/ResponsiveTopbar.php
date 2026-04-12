<?php

/**
 * Responsive Topbar Renderer
 *
 * Centralizes topbar rendering for responsive applications.
 * Handles app list fetching, permission checking, and template rendering.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

declare(strict_types=1);

namespace Horde\Core\View;

use Horde\Core\Horde;
use Horde_Perms;
use Horde_Registry;

/**
 * Responsive Topbar Renderer
 *
 * Centralizes topbar rendering for responsive applications.
 * Handles app list fetching, permission checking, and template rendering.
 *
 * Usage:
 * <code>
 * $topbar = new ResponsiveTopbar($registry, _("Tasks"));
 * $topbarHtml = $topbar->render();
 * </code>
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */
class ResponsiveTopbar
{
    private Horde_Registry $registry;
    private string $appName;

    /**
     * Constructor
     *
     * @param Horde_Registry $registry Registry instance
     * @param string $appName Localized application name to display
     */
    public function __construct(Horde_Registry $registry, string $appName)
    {
        $this->registry = $registry;
        $this->appName = $appName;
    }

    /**
     * Render topbar HTML
     *
     * @return string Rendered topbar HTML
     */
    public function render(): string
    {
        $topbarData = $this->buildTopbarData();

        $topbarTemplate = HORDE_TEMPLATES . '/responsive/topbar.html.php';
        $topbarView = new ResponsiveTemplateView($topbarTemplate, $topbarData);

        return $topbarView->render();
    }

    /**
     * Build topbar data structure
     *
     * Fetches all active applications, filters by permissions,
     * and categorizes into top-level apps and hamburger menu items.
     *
     * @return array Topbar data for template with keys:
     *               - appName: Current application name
     *               - portalUrl: URL to portal
     *               - logoutUrl: URL to logout
     *               - userName: Logged in username
     *               - topLevelApps: Array of top-level apps for topbar
     *               - allApps: Array of all apps for hamburger menu
     */
    private function buildTopbarData(): array
    {
        // Get all active apps
        $allApps = $this->registry->listApps(['active', 'admin', 'noadmin', 'topbar'], true, null);

        // Separate apps into top-level and submenu items
        $topLevelApps = [];
        $allAppsList = [];

        foreach ($allApps as $app => $params) {
            // Skip horde itself
            if ($app === 'horde') {
                continue;
            }

            // Skip topbar-only items (they're app-specific widgets)
            // IMPORTANT: Must check this BEFORE hasPermission()
            // because topbar items like 'kronolith-menu' don't have APIs
            if ($params['status'] === 'topbar') {
                continue;
            }

            // Skip if user doesn't have permission
            if (!$this->registry->hasPermission($app, Horde_Perms::SHOW)) {
                continue;
            }

            $appData = [
                'name' => strlen($params['name'] ?? '') ? _($params['name']) : '',
                'url' => (string) Horde::url($this->registry->getInitialPage($app), true, ['app' => $app]),
                'icon' => $params['icon'] ?? $this->registry->get('icon', $app),
                'app' => $app, // Add app identifier for CSS class
            ];

            // Add to all apps list (for hamburger menu)
            $allAppsList[] = $appData;

            // Top-level apps (no menu_parent) go to topbar
            if (empty($params['menu_parent'])) {
                $topLevelApps[] = $appData;
            }
        }

        return [
            'appName' => $this->appName,
            'portalUrl' => (string) $this->registry->getServiceLink('portal')->setRaw(true),
            'logoutUrl' => (string) $this->registry->getServiceLink('logout')->setRaw(true),
            'userName' => $this->registry->getAuth(),
            'topLevelApps' => $topLevelApps,
            'allApps' => $allAppsList,
        ];
    }
}
