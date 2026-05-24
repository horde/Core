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

use Horde\Core\Assets\GraphicDiscoverer;
use Horde\Core\Horde;
use Horde_Perms;
use Horde_Registry;
use Throwable;

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
    private ?GraphicDiscoverer $graphicDiscoverer;
    private string $theme;

    /**
     * Constructor
     *
     * @param Horde_Registry $registry Registry instance
     * @param string $appName Localized application name to display
     * @param GraphicDiscoverer|null $graphicDiscoverer Resolves app icon graphics through theme cascade
     * @param string $theme Current theme name for icon resolution
     */
    public function __construct(
        Horde_Registry $registry,
        string $appName,
        ?GraphicDiscoverer $graphicDiscoverer = null,
        string $theme = 'default',
    ) {
        $this->registry = $registry;
        $this->appName = $appName;
        $this->graphicDiscoverer = $graphicDiscoverer;
        $this->theme = $theme;
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

        $html = '';

        $iconCss = $this->buildIconCss($topbarData['allApps']);
        if ($iconCss !== '') {
            $html .= '<style>' . $iconCss . '</style>' . "\n";
        }

        $html .= $topbarView->render();

        return $html;
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

            try {
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
            } catch (Throwable $e) {
                Horde::log($e);
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

    /**
     * Build inline CSS for app icons in the topbar menu.
     *
     * Uses GraphicDiscoverer to resolve each app's icon through the
     * theme cascade (app+theme → app+default → horde+theme → horde+default).
     * This ensures all app icons are available regardless of which app is active.
     *
     * @param array $apps App data from buildTopbarData
     * @return string CSS rules for app icons, empty string if no discoverer
     */
    private function buildIconCss(array $apps): string
    {
        if ($this->graphicDiscoverer === null) {
            return '';
        }

        $rules = [];
        foreach ($apps as $app) {
            $appName = $app['app'];
            $iconFile = $appName . '.png';

            $iconUri = $this->graphicDiscoverer->resolve($iconFile, $this->theme, $appName);
            if ($iconUri === null) {
                continue;
            }

            $escapedUri = htmlspecialchars($iconUri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $rules[] = '.topbar-menu-app-' . $appName . '::before{'
                . "content:'';"
                . 'display:inline-block;'
                . 'width:20px;'
                . 'height:20px;'
                . "background-image:url('" . $escapedUri . "');"
                . 'background-size:contain;'
                . 'background-repeat:no-repeat;'
                . 'background-position:center;'
                . 'vertical-align:middle;'
                . 'margin-right:8px;'
                . '}';
        }

        return implode("\n", $rules);
    }
}
