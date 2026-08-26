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

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\Assets\ThemeResolver;
use Horde\Core\Session\SessionAccess;
use Horde\Core\Sidebar\SidebarRenderer;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Injector\Injector;

class DesktopChromeRendererFactory
{
    public function create(Injector $injector): DesktopChromeRenderer
    {
        $session = $injector->getInstance(SessionAccess::class);
        $themeResolver = $injector->get(ThemeResolver::class);

        $authUid = $session->getAuthId();
        $theme = $authUid !== null ? $themeResolver->resolve($authUid) : 'default';

        return new DesktopChromeRenderer(
            $injector->getInstance(AssetCollector::class),
            $injector->getInstance(PageComposer::class),
            $injector->getInstance(ViewModeConfigurator::class),
            $injector->getInstance(TopbarBuilder::class),
            $injector->getInstance(TopbarRenderer::class),
            $injector->getInstance(SidebarRenderer::class),
            $injector->getInstance(JsDiscoverer::class),
            $theme,
        );
    }
}
