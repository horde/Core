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
use Horde\Core\Assets\GraphicDiscoverer;
use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\Assets\ThemeResolver;
use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\View\ResponsiveTopbar;
use Horde\Injector\Injector;
use Horde_Registry;

class ResponsiveChromeRendererFactory
{
    public function create(Injector $injector): ResponsiveChromeRenderer
    {
        $registry = $injector->get(Horde_Registry::class);
        $registryState = $injector->get(RegistryConfigLoader::class)->load();
        $graphicDiscoverer = $injector->get(GraphicDiscoverer::class);
        $themeResolver = $injector->get(ThemeResolver::class);

        $authUid = $registry->getAuth();
        $theme = $authUid ? $themeResolver->resolve($authUid) : 'default';

        $topbarFactory = static function (string $app) use ($registry, $registryState, $graphicDiscoverer, $theme): string {
            $appConfig = $registryState->getApplication($app);
            $appName = $appConfig['name'] ?? $app;
            $topbar = new ResponsiveTopbar($registry, $appName, $graphicDiscoverer, $theme);
            return $topbar->render();
        };

        return new ResponsiveChromeRenderer(
            $injector->get(CssDiscoverer::class),
            $injector->get(JsDiscoverer::class),
            $topbarFactory,
            $theme,
        );
    }
}
