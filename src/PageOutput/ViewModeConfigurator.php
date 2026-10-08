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

use Horde\Core\Assets\CssDiscoverer;
use Horde\Core\Assets\CssDiscoveryRequest;
use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\Assets\JsDiscoveryRequest;
use Horde\Core\Assets\ThemeResolver;
use Horde\Core\Service\PrefsService;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionAccess;
use Horde\Injector\Attribute\Factory;
use Horde\Token\Token;
use Horde_Registry;
use Exception;

/**
 * Populates an AssetCollector with scripts and JS vars for a given view mode.
 *
 * Replaces the inline logic from Horde_PageOutput::header() and
 * Horde_PageOutput::_addBasicScripts() for the BASIC and DYNAMIC modes.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: ViewModeConfiguratorFactory::class, method: 'create')]
class ViewModeConfigurator
{
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly PrefsService $prefs,
        private readonly SessionAccess $session,
        private readonly JsDiscoverer $jsDiscoverer,
        private readonly Token $tokenService,
        private readonly ?CssDiscoverer $cssDiscoverer = null,
        private readonly ?ThemeResolver $themeResolver = null,
    ) {}

    /**
     * Adds themed stylesheets to the output.
     *
     * Resolves the user's theme and walks the asset cascade, so a theme that
     * does not ship $files still gets the default theme's version, and one
     * that does gets it layered on top.
     *
     * Controllers outside the Horde_PageOutput path used to hardcode
     * '/default/' in the stylesheet URI, which pinned those pages to the
     * default theme whatever the user had selected.
     *
     * @param list<string> $files Stylesheet names, e.g. ['screen.css'].
     */
    public function addThemeStylesheets(
        AssetCollector $collector,
        array $files,
        string $app = 'horde',
    ): void {
        if ($this->cssDiscoverer === null || $this->themeResolver === null) {
            return;
        }

        $theme = $this->resolveTheme($app);

        $result = $this->cssDiscoverer->discover(
            new CssDiscoveryRequest(files: $files, app: $app, theme: $theme)
        );

        foreach ($result as $entry) {
            $collector->addStylesheet($entry->uri);
        }
    }

    /**
     * Adds the scripts the active theme ships for itself (info.php
     * $theme_scripts), as DesktopChromeRenderer does.
     *
     * Call it AFTER configure(): theme scripts may rely on the core
     * scripts (prototype.js, horde.js) it adds.
     */
    public function addThemeScripts(AssetCollector $collector, string $app = 'horde'): void
    {
        if ($this->themeResolver === null) {
            return;
        }

        $scripts = $this->jsDiscoverer->discoverTheme(
            new JsDiscoveryRequest(app: $app, theme: $this->resolveTheme($app))
        );
        foreach ($scripts as $entry) {
            $collector->addScript($entry->uri);
        }
    }

    private function resolveTheme(string $app): string
    {
        $uid = $this->session->getAuthId() ?? '';

        return $uid === ''
            ? 'default'
            : $this->themeResolver->resolve($uid, null, $app);
    }

    public function configure(AssetCollector $collector, ViewMode $mode): void
    {
        $this->addBasicScripts($collector);

        if ($mode === ViewMode::DYNAMIC) {
            $this->addDynamicScripts($collector);
        }
    }

    private function addBasicScripts(AssetCollector $collector): void
    {
        $scripts = $this->jsDiscoverer->resolveMany([
            'prototype.js',
            'horde.js',
        ], 'horde');
        foreach ($scripts as $uri) {
            if ($uri !== null) {
                $collector->addScript($uri);
            }
        }

        $uid = $this->session->getAuthId() ?? '';
        if ($uid !== '' && $this->prefs->getValue($uid, 'horde', 'widget_accesskey')) {
            $uri = $this->jsDiscoverer->resolve('accesskeys.js', 'horde');
            if ($uri !== null) {
                $collector->addScript($uri);
            }
        }
    }

    private function addDynamicScripts(AssetCollector $collector): void
    {
        $scripts = $this->jsDiscoverer->resolveMany([
            'hordecore.js',
            'growler.js',
            'scriptaculous/effects.js',
            'scriptaculous/sound.js',
        ], 'horde');
        foreach ($scripts as $uri) {
            if ($uri !== null) {
                $collector->addScript($uri);
            }
        }

        $app = $this->registry->getApp();
        $uid = $this->session->getAuthId() ?? '';

        $jsConf = array_filter([
            'URI_AJAX' => $this->getServiceLinkUrl('ajax', $app),
            'URI_DLOAD' => $this->getServiceLinkUrl('download', $app),
            'URI_LOGOUT' => $this->getServiceLinkUrl('logout'),
            'TOKEN' => (string) $this->tokenService->generate(HordeSession::CSRF_SEED)->token,
            'growler_log' => true,
            'popup_height' => 610,
            'popup_width' => 820,
        ]);

        $jsText = [
            'ajax_error' => 'Error when communicating with the server.',
            'ajax_recover' => 'The connection to the server has been restored.',
            'ajax_timeout' => 'There has been no contact with the server for several minutes. The server may be temporarily unavailable or network problems may be interrupting your session. You will not see any updates until the connection is restored.',
            'growlerclear' => 'Clear All',
            'growlerinfo' => 'This is the notification log.',
            'growlernoalerts' => 'No Alerts',
        ];

        $collector->addJsVar('HordeCore.conf', $jsConf, true);
        $collector->addJsVar('HordeCore.text', $jsText, true);
    }

    private function getServiceLinkUrl(string $service, ?string $app = null): string
    {
        try {
            $link = $app !== null
                ? $this->registry->getServiceLink($service, $app)
                : $this->registry->getServiceLink($service);
            return (string) $link->setRaw(true);
        } catch (Exception) {
            return '';
        }
    }
}
