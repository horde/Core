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

use Horde\Core\Horde;
use Horde\Core\Service\PermissionService;
use Horde\Core\Service\PrefsService;
use Horde\Core\Session\SessionAccess;
use Horde\Injector\Attribute\Factory;
use Horde_Registry;
use DateTimeImmutable;
use Exception;
use Horde_Bundle;
use Horde_Perms;
use IntlDateFormatter;
use Throwable;

/**
 * Builds a TopbarData object from the Horde registry and services.
 *
 * Replaces the logic from Horde_Core_Topbar::getTree() — iterates
 * the registry's app list, builds the nested menu tree, and assembles
 * search config, login/logout URLs, and JS configuration.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: TopbarBuilderFactory::class, method: 'create')]
class TopbarBuilder
{
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly PrefsService $prefs,
        private readonly PermissionService $permissions,
        private readonly SessionAccess $session,
    ) {}

    public function build(string $currentApp = 'horde'): TopbarData
    {
        $portalUrl = (string) $this->registry->get('webroot', 'horde');
        $version = class_exists('Horde_Bundle')
            ? Horde_Bundle::SHORTNAME . ' ' . Horde_Bundle::VERSION
            : ($this->registry->get('version', 'horde') ?? '');

        $menuTree = $this->buildMenuTree($currentApp);

        $searchConfig = $this->buildSearchConfig($currentApp);
        $logoutUrl = $this->buildLogoutUrl();
        $loginUrl = $this->buildLoginUrl();
        $date = (new IntlDateFormatter(
            null,
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
        ))->format(new DateTimeImmutable()) ?: '';

        $uid = $this->session->getAuthId() ?? '';
        $subinfo = $uid !== '' ? $uid : null;
        $sidebarWidth = 150;
        if ($uid !== '') {
            $width = $this->prefs->getValue($uid, 'horde', 'sidebar_width');
            if ($width !== null && (int) $width > 0) {
                $sidebarWidth = (int) $width;
            }
        }

        $jsConfig = array_filter([
            'URI_AJAX' => $this->getServiceLinkUrl('ajax', 'horde'),
            'app' => $currentApp,
            'format' => $this->translateDateFormat($uid),
            'hash' => md5(serialize($menuTree)),
            'refresh' => $this->getMenuRefreshTime($uid),
        ]);

        return new TopbarData(
            portalUrl: $portalUrl,
            version: $version,
            menuTree: $menuTree,
            searchConfig: $searchConfig,
            logoutUrl: $logoutUrl,
            loginUrl: $loginUrl,
            date: $date,
            sidebarWidth: $sidebarWidth,
            jsConfig: $jsConfig,
            subinfo: $subinfo,
        );
    }

    /** @return TopbarMenuNode[] */
    private function buildMenuTree(string $currentApp): array
    {
        $apps = [];
        try {
            $apps = $this->registry->listApps(
                ['active', 'admin', 'noadmin', 'heading', 'link', 'notoolbar', 'topbar'],
                true,
                null
            );
        } catch (Exception) {
            return [];
        }

        $isAdmin = false;
        try {
            $isAdmin = $this->registry->isAdmin();
        } catch (Exception) {
        }

        $menu = [];
        $uid = $this->session->getAuthId() ?? '';

        foreach ($apps as $app => $params) {
            if ($app === 'horde') {
                continue;
            }

            $status = $params['status'] ?? '';
            if (in_array($status, ['heading', 'link'])) {
                $menu[$app] = $params;
                continue;
            }

            if (!in_array($status, ['active', 'admin', 'noadmin', 'topbar'])) {
                continue;
            }

            if ($isAdmin && $status === 'noadmin') {
                continue;
            }
            if (!$isAdmin && $status === 'admin') {
                continue;
            }

            $permApp = !empty($params['app']) ? $params['app'] : $app;
            try {
                if ($this->permissions->exists($permApp)
                    && !$this->permissions->hasPermission($permApp, $uid, ['show'])) {
                    continue;
                }
            } catch (Exception) {
            }

            $menu[$app] = $params;
        }

        $this->removeEmptyHeadings($menu);

        $flatNodes = [];
        foreach ($menu as $app => $params) {
            $status = $params['status'] ?? '';
            if ($status === 'topbar') {
                $this->callTopbarCreate($app, $params, $flatNodes);
                continue;
            }

            $name = '';
            if (strlen((string) ($params['name'] ?? ''))) {
                /* Application names live in the owning app's gettext domain;
                 * headings/links have no dedicated app domain, so keep the
                 * legacy _() behaviour for them. */
                $name = in_array($status, ['heading', 'link'], true)
                    ? _($params['name'])
                    : $this->appName($app, (string) $params['name']);
            }
            $url = '';
            if (isset($params['url'])) {
                $url = (string) $params['url'];
            } elseif ($status !== 'heading' && isset($params['webroot'])) {
                try {
                    $url = (string) $this->registry->getInitialPage($app);
                } catch (Exception) {
                }
            }

            $flatNodes[$app] = [
                'id' => $app,
                'label' => $name,
                'url' => $url ?: null,
                'iconClass' => $params['class'] ?? ($app === $currentApp ? 'horde-point-center-active' : 'horde-point-center'),
                'target' => $params['target'] ?? null,
                'onclick' => $params['onclick'] ?? null,
                'active' => ($app === $currentApp),
                'noarrow' => !empty($params['noarrow']),
                'parent' => $params['menu_parent'] ?? null,
            ];
        }

        $this->buildSettingsMenu($currentApp, $isAdmin, $uid, $flatNodes);

        return $this->nestNodes($flatNodes);
    }

    private function removeEmptyHeadings(array &$menu): void
    {
        do {
            $children = [];
            foreach ($menu as $params) {
                if (isset($params['menu_parent'])) {
                    $children[$params['menu_parent']] = true;
                }
            }

            $found = false;
            foreach (array_keys($menu) as $key) {
                if (($menu[$key]['status'] ?? '') === 'heading' && empty($children[$key])) {
                    unset($menu[$key]);
                    $found = true;
                }
            }
        } while ($found);
    }

    /** @return TopbarMenuNode[] */
    private function nestNodes(array $flatNodes): array
    {
        // Collect parent→children relationships in mutable arrays first,
        // then build immutable TopbarMenuNode objects bottom-up so each
        // node is complete (with all descendants) before being placed
        // into its parent's children array.
        $childIds = [];
        $roots = [];
        foreach ($flatNodes as $id => $data) {
            $parent = $data['parent'];
            if ($parent !== null && isset($flatNodes[$parent])) {
                $childIds[$parent][] = $id;
            } else {
                $roots[] = $id;
            }
        }

        $built = [];
        $this->buildNode($roots, $flatNodes, $childIds, $built);

        $result = [];
        foreach ($roots as $id) {
            $result[] = $built[$id];
        }
        return $result;
    }

    /**
     * Recursively build immutable nodes bottom-up.
     *
     * @param string[]                $ids      Node IDs to build at this level
     * @param array<string, array>    $flat     The flat node data
     * @param array<string, string[]> $childIds Parent→child ID mapping
     * @param array<string, TopbarMenuNode> $built  Accumulator for built nodes
     */
    private function buildNode(array $ids, array $flat, array $childIds, array &$built): void
    {
        foreach ($ids as $id) {
            $children = [];
            if (!empty($childIds[$id])) {
                $this->buildNode($childIds[$id], $flat, $childIds, $built);
                foreach ($childIds[$id] as $childId) {
                    $children[] = $built[$childId];
                }
            }

            $data = $flat[$id];
            $built[$id] = new TopbarMenuNode(
                id: $data['id'],
                label: $data['label'],
                url: $data['url'],
                iconClass: $data['iconClass'],
                target: $data['target'],
                onclick: $data['onclick'],
                active: $data['active'],
                children: $children,
                noarrow: $data['noarrow'],
            );
        }
    }

    private function buildSettingsMenu(string $currentApp, bool $isAdmin, string $uid, array &$flatNodes): void
    {
        $flatNodes['settings'] = [
            'id' => 'settings',
            'label' => '',
            'url' => null,
            'iconClass' => 'horde-settings horde-icon-settings',
            'target' => null,
            'onclick' => null,
            'active' => false,
            'noarrow' => true,
            'parent' => null,
        ];

        $this->buildAdminMenu($isAdmin, $uid, $flatNodes);
        $this->buildPrefsMenu($currentApp, $isAdmin, $flatNodes);

        if ($uid !== '') {
            $webroot = rtrim($this->registry->get('webroot', 'horde'), '/');
            $flatNodes['connected_accounts'] = [
                'id' => 'connected_accounts',
                'label' => _("Connected Accounts"),
                'url' => $webroot . '/settings/oauth/',
                'iconClass' => null,
                'target' => null,
                'onclick' => null,
                'active' => false,
                'noarrow' => false,
                'parent' => 'settings',
            ];
        }

        $flatNodes['growlerlog'] = [
            'id' => 'growlerlog',
            'label' => _("Toggle Alerts Log"),
            'url' => 'javascript:void(HordeCore.Growler.toggleLog());',
            'iconClass' => null,
            'target' => null,
            'onclick' => null,
            'active' => false,
            'noarrow' => false,
            'parent' => 'settings',
        ];

        try {
            if ($this->registry->showService('problem')) {
                $problemLink = $this->registry->getServiceLink('problem', $currentApp);
                if ($problemLink !== false) {
                    $flatNodes['problem_' . $currentApp] = [
                        'id' => 'problem_' . $currentApp,
                        'label' => _("Problem"),
                        'url' => (string) $problemLink,
                        'iconClass' => null,
                        'target' => null,
                        'onclick' => null,
                        'active' => false,
                        'noarrow' => false,
                        'parent' => 'settings',
                    ];
                }
            }
        } catch (Exception) {
        }

        try {
            if ($this->registry->showService('help')) {
                $helpLink = $this->registry->getServiceLink('help', $currentApp);
                if ($helpLink !== false) {
                    $flatNodes['help_' . $currentApp] = [
                        'id' => 'help_' . $currentApp,
                        'label' => _("Help"),
                        'url' => (string) $helpLink,
                        'iconClass' => null,
                        'target' => 'help',
                        'onclick' => null,
                        'active' => false,
                        'noarrow' => false,
                        'parent' => 'settings',
                    ];
                }
            }
        } catch (Exception) {
        }
    }

    private function buildAdminMenu(bool $isAdmin, string $uid, array &$flatNodes): void
    {
        $adminItemCount = 0;

        try {
            $adminList = $this->registry->callByPackage('horde', 'admin_list');
        } catch (Exception) {
            return;
        }

        if (!is_array($adminList)) {
            return;
        }

        foreach ($adminList as $method => $val) {
            $permName = 'horde:administration:' . $method;
            $hasAccess = $isAdmin;

            if (!$hasAccess) {
                try {
                    $hasAccess = $this->permissions->exists($permName)
                        && $this->permissions->hasPermission($permName, $uid, ['show']);
                } catch (Exception) {
                }
            }

            if (!$hasAccess) {
                continue;
            }

            ++$adminItemCount;
            $name = Horde::stripAccessKey($val['name']);
            $url = $this->registry->applicationWebPath($val['link'], 'horde');

            $flatNodes['administration_' . $method] = [
                'id' => 'administration_' . $method,
                'label' => $name,
                'url' => $url,
                'iconClass' => null,
                'target' => null,
                'onclick' => null,
                'active' => false,
                'noarrow' => false,
                'parent' => 'administration',
            ];
        }

        if ($adminItemCount > 0) {
            $flatNodes['administration'] = [
                'id' => 'administration',
                'label' => _("Administration"),
                'url' => null,
                'iconClass' => null,
                'target' => null,
                'onclick' => null,
                'active' => false,
                'noarrow' => false,
                'parent' => 'settings',
            ];
        }
    }

    private function buildPrefsMenu(string $currentApp, bool $isAdmin, array &$flatNodes): void
    {
        try {
            if (!$this->registry->showService('prefs')) {
                return;
            }
        } catch (Exception) {
            return;
        }

        try {
            $prefsLink = $this->registry->getServiceLink('prefs', $currentApp);
        } catch (Exception) {
            return;
        }

        if ($prefsLink === false) {
            return;
        }

        $flatNodes['prefs'] = [
            'id' => 'prefs',
            'label' => _("Preferences"),
            'url' => (string) $prefsLink,
            'iconClass' => null,
            'target' => null,
            'onclick' => null,
            'active' => false,
            'noarrow' => false,
            'parent' => 'settings',
        ];

        try {
            $prefsApps = $this->registry->listApps(
                ['active', $isAdmin ? 'admin' : 'noadmin'],
                true,
                Horde_Perms::READ
            );
        } catch (Exception) {
            return;
        }

        if (!empty($prefsApps['horde'])) {
            $hordePrefsLink = $this->registry->getServiceLink('prefs', 'horde');
            if ($hordePrefsLink !== false) {
                $flatNodes['prefs_horde'] = [
                    'id' => 'prefs_horde',
                    'label' => _("Global Preferences"),
                    'url' => (string) $hordePrefsLink,
                    'iconClass' => null,
                    'target' => null,
                    'onclick' => null,
                    'active' => false,
                    'noarrow' => false,
                    'parent' => 'prefs',
                ];
            }
            unset($prefsApps['horde']);
        }

        /* Resolve each app's name via its own gettext domain before sorting;
         * the comparator only sees values, not the app key, and _() would
         * resolve against whatever default domain is active here. */
        foreach ($prefsApps as $app => &$params) {
            if (strlen((string) ($params['name'] ?? ''))) {
                $params['name'] = $this->appName($app, (string) $params['name']);
            }
        }
        unset($params);

        uasort($prefsApps, static function ($a, $b) {
            return strcoll((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        foreach ($prefsApps as $app => $params) {
            try {
                $appPrefsLink = $this->registry->getServiceLink('prefs', $app);
            } catch (Exception) {
                continue;
            }
            if ($appPrefsLink === false) {
                continue;
            }

            $flatNodes['prefs_' . $app] = [
                'id' => 'prefs_' . $app,
                'label' => $params['name'],
                'url' => (string) $appPrefsLink,
                'iconClass' => null,
                'target' => null,
                'onclick' => null,
                'active' => false,
                'noarrow' => false,
                'parent' => 'prefs',
            ];
        }
    }

    /**
     * Translate an application's display name using that application's own
     * gettext domain.
     *
     * The registry stores raw msgids for application names; the translations
     * live in each app's own catalog, not in Core. Plain _() resolves against
     * the active default domain, which may belong to a different app by this
     * point in the request, so non-current apps would fall back to the raw
     * English name. dgettext() targets an explicit domain, but only resolves
     * if that domain was bound this request (an app that was never pushed has
     * no binding), so bind it first. dgettext() does not change the active
     * domain, so no textdomain() save/restore is needed.
     */
    private function appName(string $app, string $name): string
    {
        if ($name === '') {
            return '';
        }

        bindtextdomain($app, (string) $this->registry->get('fileroot', $app) . '/locale');
        if (function_exists('bind_textdomain_codeset')) {
            bind_textdomain_codeset($app, 'UTF-8');
        }

        return dgettext($app, $name);
    }

    private function callTopbarCreate(string $app, array $params, array &$flatNodes): void
    {
        $realApp = $params['app'] ?? $app;
        $parent = !empty($params['menu_parent']) ? $params['menu_parent'] : null;
        $topbarParams = $params['topbar_params'] ?? [];

        try {
            $adapter = new LegacyTreeAdapter();
            $this->registry->callAppMethod(
                $realApp,
                'topbarCreate',
                [
                    'args' => [
                        $adapter,
                        $parent,
                        $topbarParams,
                    ],
                ]
            );

            foreach ($adapter->getCollectedNodes() as $node) {
                $nodeParams = $node->params;
                $flatNodes[$node->id] = [
                    'id' => $node->id,
                    'label' => $node->label,
                    'url' => $nodeParams['url'] ?? null,
                    'iconClass' => $nodeParams['class'] ?? null,
                    'target' => $nodeParams['target'] ?? null,
                    'onclick' => $nodeParams['onclick'] ?? null,
                    'active' => $nodeParams['active'] ?? false,
                    'noarrow' => $nodeParams['noarrow'] ?? false,
                    'parent' => $node->parentId,
                ];
            }
        } catch (Throwable) {
        }
    }

    private function buildSearchConfig(string $currentApp): ?TopbarSearchConfig
    {
        try {
            $searchUrl = $this->registry->getServiceLink('search', $currentApp);
            if ($searchUrl === false) {
                return null;
            }
            return new TopbarSearchConfig(
                action: (string) $searchUrl,
                label: 'Search',
                iconUrl: (string) $this->registry->get('themesuri', 'horde') . '/graphics/search.png',
            );
        } catch (Exception) {
            return null;
        }
    }

    private function buildLogoutUrl(): ?string
    {
        try {
            $uid = $this->session->getAuthId();
            if ($uid === null) {
                return null;
            }
            $link = $this->registry->getServiceLink('logout');
            return $link !== false ? (string) $link->setRaw(true) : null;
        } catch (Exception) {
            return null;
        }
    }

    private function buildLoginUrl(): ?string
    {
        try {
            $uid = $this->session->getAuthId();
            if ($uid !== null) {
                return null;
            }
            $link = $this->registry->getServiceLink('login');
            return $link !== false ? (string) $link->setRaw(true) : null;
        } catch (Exception) {
            return null;
        }
    }

    private function getServiceLinkUrl(string $service, ?string $app = null): string
    {
        try {
            $link = $app !== null
                ? $this->registry->getServiceLink($service, $app)
                : $this->registry->getServiceLink($service);
            return $link !== false ? (string) $link->setRaw(true) : '';
        } catch (Exception) {
            return '';
        }
    }

    private function translateDateFormat(string $uid): string
    {
        $format = '';
        if ($uid !== '') {
            $format = $this->prefs->getValue($uid, 'horde', 'date_format') ?? '';
        }
        if ($format === '') {
            $format = '%x';
        }

        $from = ['%e', '%-d', '%d', '%a', '%A', '%-m', '%m', '%h', '%b', '%B', '%y', '%Y'];
        $to = [' d', 'd', 'dd', 'ddd', 'dddd', 'M', 'MM', 'MMM', 'MMM', 'MMMM', 'yy', 'yyyy'];

        return str_replace($from, $to, $format);
    }

    private function getMenuRefreshTime(string $uid): int
    {
        if ($uid === '') {
            return 0;
        }
        $val = $this->prefs->getValue($uid, 'horde', 'menu_refresh_time');

        return $val !== null ? (int) $val : 0;
    }
}
