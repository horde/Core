<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Sidebar;

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\Assets\JsDiscoveryRequest;
use Horde\Core\Assets\JsDiscoveryResult;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\Sidebar\SidebarContainer;
use Horde\Core\Sidebar\SidebarData;
use Horde\Core\Sidebar\SidebarHeader;
use Horde\Core\Sidebar\SidebarRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SidebarRenderer::class)]
class SidebarRendererScriptRegistrationTest extends TestCase
{
    private AssetCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new AssetCollector();
    }

    private function createDiscoverer(array $resolveMap): JsDiscoverer
    {
        return new class ($resolveMap) implements JsDiscoverer {
            public function __construct(private readonly array $map) {}

            public function resolve(string $file, string $app = 'horde'): ?string
            {
                return $this->map[$file] ?? null;
            }

            public function resolveMany(array $files, string $app = 'horde'): array
            {
                $result = [];
                foreach ($files as $f) {
                    $result[$f] = $this->map[$f] ?? null;
                }
                return $result;
            }

            public function discoverTheme(JsDiscoveryRequest $request): JsDiscoveryResult
            {
                return new JsDiscoveryResult([], $request->theme, $request->app);
            }
        };
    }

    #[Test]
    public function renderRegistersSidebarJs(): void
    {
        $discoverer = $this->createDiscoverer([
            'sidebar.js' => '/js/sidebar.js',
        ]);
        $renderer = new SidebarRenderer($this->collector, $discoverer);
        $renderer->render(new SidebarData());

        self::assertContains('/js/sidebar.js', $this->collector->getScriptUrls());
    }

    #[Test]
    public function renderRegistersEffectsWhenHeadersPresent(): void
    {
        $discoverer = $this->createDiscoverer([
            'sidebar.js' => '/js/sidebar.js',
            'scriptaculous/effects.js' => '/js/scriptaculous/effects.js',
        ]);
        $header = new SidebarHeader(id: 'test', label: 'Test');
        $container = new SidebarContainer(header: $header);
        $data = new SidebarData(containers: [$container]);

        $renderer = new SidebarRenderer($this->collector, $discoverer);
        $renderer->render($data);

        self::assertContains('/js/scriptaculous/effects.js', $this->collector->getScriptUrls());
    }

    #[Test]
    public function renderSkipsEffectsWhenNoHeaders(): void
    {
        $discoverer = $this->createDiscoverer([
            'sidebar.js' => '/js/sidebar.js',
            'scriptaculous/effects.js' => '/js/scriptaculous/effects.js',
        ]);
        $container = new SidebarContainer();
        $data = new SidebarData(containers: [$container]);

        $renderer = new SidebarRenderer($this->collector, $discoverer);
        $renderer->render($data);

        self::assertNotContains('/js/scriptaculous/effects.js', $this->collector->getScriptUrls());
    }

    #[Test]
    public function renderAddsSidebarTextVar(): void
    {
        $discoverer = $this->createDiscoverer([]);
        $renderer = new SidebarRenderer($this->collector, $discoverer);
        $renderer->render(new SidebarData());

        $vars = $this->collector->renderJsVarBlock();
        self::assertStringContainsString('HordeSidebar.text=', $vars);
        self::assertStringContainsString('"collapse"', $vars);
        self::assertStringContainsString('"expand"', $vars);
    }

    #[Test]
    public function renderAddsSidebarOptsVar(): void
    {
        $GLOBALS['conf']['cookie']['domain'] = '.example.com';
        $GLOBALS['conf']['cookie']['path'] = '/horde';

        try {
            $discoverer = $this->createDiscoverer([]);
            $renderer = new SidebarRenderer($this->collector, $discoverer);
            $renderer->render(new SidebarData());

            $vars = $this->collector->renderJsVarBlock();
            self::assertStringContainsString('HordeSidebar.opts=', $vars);
            self::assertStringContainsString('.example.com', $vars);
            self::assertStringContainsString('/horde', $vars);
        } finally {
            unset($GLOBALS['conf']);
        }
    }

    #[Test]
    public function renderOptsDefaultsWhenNoConf(): void
    {
        unset($GLOBALS['conf']);

        $discoverer = $this->createDiscoverer([]);
        $renderer = new SidebarRenderer($this->collector, $discoverer);
        $renderer->render(new SidebarData());

        $vars = $this->collector->renderJsVarBlock();
        self::assertStringContainsString('HordeSidebar.opts=', $vars);
    }

    #[Test]
    public function renderSkipsUnresolvedSidebarJs(): void
    {
        $discoverer = $this->createDiscoverer([]);
        $renderer = new SidebarRenderer($this->collector, $discoverer);
        $renderer->render(new SidebarData());

        self::assertEmpty($this->collector->getScriptUrls());
    }
}
