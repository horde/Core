<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\PageOutput;

use Horde\Core\PageOutput\ChromeRendererDispatcher;
use Horde\Core\PageOutput\DesktopChromeRenderer;
use Horde\Core\PageOutput\PageContent;
use Horde\Core\PageOutput\RenderingMode;
use Horde\Core\PageOutput\ResponsiveChromeRenderer;
use Horde\Http\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChromeRendererDispatcher::class)]
class ChromeRendererDispatcherTest extends TestCase
{
    private DesktopChromeRenderer $desktop;
    private ResponsiveChromeRenderer $responsive;

    protected function setUp(): void
    {
        $this->desktop = $this->createMock(DesktopChromeRenderer::class);
        $this->responsive = $this->createMock(ResponsiveChromeRenderer::class);
    }

    private function createDispatcher(): ChromeRendererDispatcher
    {
        return new ChromeRendererDispatcher($this->desktop, $this->responsive);
    }

    private function createRequest(RenderingMode $mode): ServerRequest
    {
        $request = new ServerRequest('GET', 'http://localhost/', [], null, '1.1', []);
        return $request->withAttribute('renderingMode', $mode);
    }

    #[Test]
    public function responsiveModeDispatchesToResponsiveRenderer(): void
    {
        $this->responsive->expects(self::once())
            ->method('renderPage')
            ->willReturn('<responsive-html>');
        $this->desktop->expects(self::never())->method('renderPage');

        $dispatcher = $this->createDispatcher();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $result = $dispatcher->renderPage($content, $this->createRequest(RenderingMode::RESPONSIVE));

        self::assertSame('<responsive-html>', $result);
    }

    #[Test]
    public function dynamicModeDispatchesToDesktopRenderer(): void
    {
        $this->desktop->expects(self::once())
            ->method('renderPage')
            ->willReturn('<desktop-html>');
        $this->responsive->expects(self::never())->method('renderPage');

        $dispatcher = $this->createDispatcher();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $result = $dispatcher->renderPage($content, $this->createRequest(RenderingMode::DYNAMIC));

        self::assertSame('<desktop-html>', $result);
    }

    #[Test]
    public function basicModeDispatchesToDesktopRenderer(): void
    {
        $this->desktop->expects(self::once())
            ->method('renderPage')
            ->willReturn('<desktop-html>');
        $this->responsive->expects(self::never())->method('renderPage');

        $dispatcher = $this->createDispatcher();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $result = $dispatcher->renderPage($content, $this->createRequest(RenderingMode::BASIC));

        self::assertSame('<desktop-html>', $result);
    }

    #[Test]
    public function missingModeAttributeDefaultsToDynamic(): void
    {
        $this->desktop->expects(self::once())
            ->method('renderPage')
            ->willReturn('<desktop-html>');
        $this->responsive->expects(self::never())->method('renderPage');

        $dispatcher = $this->createDispatcher();
        $content = new PageContent(title: 'Test', bodyHtml: '');

        $request = new ServerRequest('GET', 'http://localhost/', [], null, '1.1', []);
        $result = $dispatcher->renderPage($content, $request);

        self::assertSame('<desktop-html>', $result);
    }

    #[Test]
    public function dispatcherPassesContentAndRequestThrough(): void
    {
        $content = new PageContent(title: 'My Title', bodyHtml: '<p>Content</p>', app: 'nag');
        $request = $this->createRequest(RenderingMode::RESPONSIVE);

        $this->responsive->expects(self::once())
            ->method('renderPage')
            ->with($content, $request)
            ->willReturn('<html>');

        $dispatcher = $this->createDispatcher();
        $dispatcher->renderPage($content, $request);
    }
}
