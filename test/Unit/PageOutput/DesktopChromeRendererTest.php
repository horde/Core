<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\PageOutput;

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\DesktopChromeRenderer;
use Horde\Core\PageOutput\PageComposer;
use Horde\Core\PageOutput\PageContent;
use Horde\Core\PageOutput\RenderingMode;
use Horde\Core\PageOutput\ViewMode;
use Horde\Core\PageOutput\ViewModeConfigurator;
use Horde\Core\Sidebar\SidebarData;
use Horde\Core\Sidebar\SidebarRenderer;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Core\Topbar\TopbarData;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Http\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DesktopChromeRenderer::class)]
class DesktopChromeRendererTest extends TestCase
{
    private AssetCollector $collector;
    private PageComposer $pageComposer;
    private ViewModeConfigurator $configurator;
    private TopbarBuilder $topbarBuilder;
    private TopbarRenderer $topbarRenderer;
    private SidebarRenderer $sidebarRenderer;
    private JsDiscoverer $jsDiscoverer;

    protected function setUp(): void
    {
        $this->collector = new AssetCollector();

        $this->pageComposer = $this->createMock(PageComposer::class);
        $this->pageComposer->method('renderHead')->willReturn('<head-html>');
        $this->pageComposer->method('renderFoot')->willReturn('<foot-html>');

        $this->configurator = $this->createMock(ViewModeConfigurator::class);

        $this->topbarBuilder = $this->createMock(TopbarBuilder::class);
        $this->topbarBuilder->method('build')->willReturn(
            new TopbarData(portalUrl: '/horde/', version: 'H6')
        );

        $this->topbarRenderer = $this->createMock(TopbarRenderer::class);
        $this->topbarRenderer->method('render')->willReturn('<topbar-html>');

        $this->sidebarRenderer = $this->createMock(SidebarRenderer::class);
        $this->sidebarRenderer->method('render')->willReturn('<sidebar-html>');

        $this->jsDiscoverer = $this->createMock(JsDiscoverer::class);
        $this->jsDiscoverer->method('resolve')->willReturn(null);
    }

    private function createRenderer(): DesktopChromeRenderer
    {
        return new DesktopChromeRenderer(
            $this->collector,
            $this->pageComposer,
            $this->configurator,
            $this->topbarBuilder,
            $this->topbarRenderer,
            $this->sidebarRenderer,
            $this->jsDiscoverer,
        );
    }

    private function createRequest(RenderingMode $mode = RenderingMode::DYNAMIC): ServerRequest
    {
        $request = new ServerRequest('GET', 'http://localhost/horde/', [], null, '1.1', []);
        return $request->withAttribute('renderingMode', $mode);
    }

    #[Test]
    public function renderPageProducesHeadTopbarBodyFoot(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test Page', bodyHtml: '<div>Body</div>');

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringContainsString('<head-html>', $html);
        self::assertStringContainsString('<topbar-html>', $html);
        self::assertStringContainsString('<div>Body</div>', $html);
        self::assertStringContainsString('<foot-html>', $html);
    }

    #[Test]
    public function renderPageOrderIsCorrect(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '<body-content>');

        $html = $renderer->renderPage($content, $this->createRequest());

        $headPos = strpos($html, '<head-html>');
        $topbarPos = strpos($html, '<topbar-html>');
        $bodyPos = strpos($html, '<body-content>');
        $footPos = strpos($html, '<foot-html>');

        self::assertLessThan($topbarPos, $headPos);
        self::assertLessThan($bodyPos, $topbarPos);
        self::assertLessThan($footPos, $bodyPos);
    }

    #[Test]
    public function renderPageIncludesSidebarWhenProvided(): void
    {
        $renderer = $this->createRenderer();
        $sidebarData = new SidebarData();
        $content = new PageContent(
            title: 'Test',
            bodyHtml: '<div>Body</div>',
            sidebarData: $sidebarData,
        );

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringContainsString('<sidebar-html>', $html);
    }

    #[Test]
    public function renderPageSkipsSidebarWhenNull(): void
    {
        $this->sidebarRenderer->expects(self::never())->method('render');

        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '<div>Body</div>');

        $renderer->renderPage($content, $this->createRequest());
    }

    #[Test]
    public function renderPageConfiguresViewModeFromRequest(): void
    {
        $this->configurator->expects(self::once())
            ->method('configure')
            ->with($this->collector, ViewMode::BASIC);

        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $renderer->renderPage($content, $this->createRequest(RenderingMode::BASIC));
    }

    #[Test]
    public function renderPageDynamicModeUsesViewModeDynamic(): void
    {
        $this->configurator->expects(self::once())
            ->method('configure')
            ->with($this->collector, ViewMode::DYNAMIC);

        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $renderer->renderPage($content, $this->createRequest(RenderingMode::DYNAMIC));
    }

    #[Test]
    public function renderPageResolvesExtraJsFiles(): void
    {
        $jsDiscoverer = $this->createMock(JsDiscoverer::class);
        $jsDiscoverer->method('resolve')->willReturnCallback(function (string $file, string $app) {
            return '/js/' . $app . '/' . $file;
        });
        $this->jsDiscoverer = $jsDiscoverer;

        $renderer = $this->createRenderer();
        $content = new PageContent(
            title: 'Test',
            bodyHtml: '',
            app: 'nag',
            jsFiles: ['tasks.js'],
        );

        $renderer->renderPage($content, $this->createRequest());

        $urls = $this->collector->getScriptUrls();
        self::assertContains('/js/nag/tasks.js', $urls);
    }

    #[Test]
    public function renderPageAddsExtraCssFiles(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(
            title: 'Test',
            bodyHtml: '',
            cssFiles: ['/themes/default/custom.css'],
        );

        $renderer->renderPage($content, $this->createRequest());

        $urls = $this->collector->getStylesheetUrls();
        self::assertContains('/themes/default/custom.css', $urls);
    }

    #[Test]
    public function renderPageBuildsTopbarWithCorrectApp(): void
    {
        $this->topbarBuilder->expects(self::once())
            ->method('build')
            ->with('imp')
            ->willReturn(new TopbarData(portalUrl: '/horde/', version: 'H6'));

        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Mail', bodyHtml: '', app: 'imp');

        $renderer->renderPage($content, $this->createRequest());
    }

    #[Test]
    public function renderPageDefaultsModeWhenAttributeMissing(): void
    {
        $this->configurator->expects(self::once())
            ->method('configure')
            ->with($this->collector, ViewMode::DYNAMIC);

        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $request = new ServerRequest('GET', 'http://localhost/', [], null, '1.1', []);
        $renderer->renderPage($content, $request);
    }
}
