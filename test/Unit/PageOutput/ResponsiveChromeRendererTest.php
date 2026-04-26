<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\PageOutput;

use Horde\Core\Assets\CssAssetEntry;
use Horde\Core\Assets\CssDiscoverer;
use Horde\Core\Assets\CssDiscoveryResult;
use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\PageOutput\PageContent;
use Horde\Core\PageOutput\RenderingMode;
use Horde\Core\PageOutput\ResponsiveChromeRenderer;
use Horde\Http\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponsiveChromeRenderer::class)]
class ResponsiveChromeRendererTest extends TestCase
{
    private CssDiscoverer $cssDiscoverer;
    private JsDiscoverer $jsDiscoverer;

    protected function setUp(): void
    {
        $this->cssDiscoverer = $this->createMock(CssDiscoverer::class);
        $this->cssDiscoverer->method('discover')->willReturn(
            new CssDiscoveryResult(
                [new CssAssetEntry('/path/responsive.css', '/themes/responsive.css')],
                'default',
                'horde',
            )
        );

        $this->jsDiscoverer = $this->createMock(JsDiscoverer::class);
        $this->jsDiscoverer->method('resolve')->willReturnCallback(function (string $file) {
            return '/js/' . $file;
        });
    }

    private function createRenderer(): ResponsiveChromeRenderer
    {
        $topbarFactory = static fn(string $app): string => '<nav class="topbar">Topbar for ' . $app . '</nav>';

        return new ResponsiveChromeRenderer(
            $this->cssDiscoverer,
            $this->jsDiscoverer,
            $topbarFactory,
        );
    }

    private function createRequest(): ServerRequest
    {
        $request = new ServerRequest('GET', 'http://localhost/horde/', [], null, '1.1', []);
        return $request->withAttribute('renderingMode', RenderingMode::RESPONSIVE);
    }

    #[Test]
    public function renderPageProducesFullHtmlDocument(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test Page', bodyHtml: '<div>Body</div>');

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringContainsString('</html>', $html);
    }

    #[Test]
    public function renderPageIncludesViewportMeta(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringContainsString('name="viewport"', $html);
        self::assertStringContainsString('width=device-width', $html);
    }

    #[Test]
    public function renderPageIncludesEscapedTitle(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Tasks <script>', bodyHtml: '');

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringContainsString('<title>Tasks &lt;script&gt;</title>', $html);
    }

    #[Test]
    public function renderPageIncludesCssLinks(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringContainsString('rel="stylesheet"', $html);
        self::assertStringContainsString('href="/themes/responsive.css"', $html);
    }

    #[Test]
    public function renderPageIncludesBodyContent(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '<div class="content">Hello</div>');

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringContainsString('<div class="content">Hello</div>', $html);
    }

    #[Test]
    public function renderPageIncludesTopbarJsAndResponsiveJs(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '', app: 'horde');

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringContainsString('src="/js/responsive-topbar.js"', $html);
        self::assertStringContainsString('src="/js/responsive.js"', $html);
    }

    #[Test]
    public function renderPageIncludesExtraJsFiles(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(
            title: 'Test',
            bodyHtml: '',
            app: 'nag',
            jsFiles: ['tasks.js'],
        );

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringContainsString('src="/js/tasks.js"', $html);
    }

    #[Test]
    public function renderPageCssBeforeBodyJsAfterBody(): void
    {
        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '<main>Body</main>');

        $html = $renderer->renderPage($content, $this->createRequest());

        $cssPos = strpos($html, 'rel="stylesheet"');
        $bodyPos = strpos($html, '<main>Body</main>');
        $jsPos = strpos($html, '<script src=');

        self::assertLessThan($bodyPos, $cssPos);
        self::assertLessThan($jsPos, $bodyPos);
    }

    #[Test]
    public function renderPageHandlesNoCssResults(): void
    {
        $cssDiscoverer = $this->createMock(CssDiscoverer::class);
        $cssDiscoverer->method('discover')->willReturn(
            new CssDiscoveryResult([], 'default', 'horde')
        );
        $this->cssDiscoverer = $cssDiscoverer;

        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringNotContainsString('rel="stylesheet"', $html);
    }

    #[Test]
    public function renderPageHandlesNoJsResolutions(): void
    {
        $jsDiscoverer = $this->createMock(JsDiscoverer::class);
        $jsDiscoverer->method('resolve')->willReturn(null);
        $this->jsDiscoverer = $jsDiscoverer;

        $renderer = $this->createRenderer();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $html = $renderer->renderPage($content, $this->createRequest());

        self::assertStringNotContainsString('<script', $html);
    }
}
