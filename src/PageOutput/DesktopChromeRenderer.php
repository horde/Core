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
use Horde\Core\Sidebar\SidebarRenderer;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Injector\Attribute\Factory;
use Psr\Http\Message\ServerRequestInterface;

#[Factory(factory: DesktopChromeRendererFactory::class, method: 'create')]
class DesktopChromeRenderer implements ChromeRenderer
{
    public function __construct(
        private readonly AssetCollector $assetCollector,
        private readonly PageComposer $pageComposer,
        private readonly ViewModeConfigurator $configurator,
        private readonly TopbarBuilder $topbarBuilder,
        private readonly TopbarRenderer $topbarRenderer,
        private readonly SidebarRenderer $sidebarRenderer,
        private readonly JsDiscoverer $jsDiscoverer,
    ) {}

    public function renderPage(PageContent $content, ServerRequestInterface $request): string
    {
        $mode = $request->getAttribute('renderingMode', RenderingMode::DYNAMIC);

        $this->configurator->configure($this->assetCollector, $mode->toViewMode());

        foreach ($content->cssFiles as $url) {
            $this->assetCollector->addStylesheet($url);
        }

        foreach ($content->jsFiles as $file) {
            $uri = $this->jsDiscoverer->resolve($file, $content->app);
            if ($uri !== null) {
                $this->assetCollector->addScript($uri);
            }
        }

        $meta = new PageMeta(title: $content->title);
        $html = $this->pageComposer->renderHead($meta);

        $topbarData = $this->topbarBuilder->build($content->app);
        $html .= $this->topbarRenderer->render($topbarData);

        $html .= $content->bodyHtml;

        if ($content->sidebarData !== null) {
            $html .= $this->sidebarRenderer->render($content->sidebarData);
        }

        $html .= $this->pageComposer->renderFoot();

        return $html;
    }
}
