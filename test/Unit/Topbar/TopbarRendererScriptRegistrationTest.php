<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Topbar;

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\Assets\JsDiscoveryRequest;
use Horde\Core\Assets\JsDiscoveryResult;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\Topbar\TopbarData;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Core\Topbar\TopbarSearchConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopbarRenderer::class)]
class TopbarRendererScriptRegistrationTest extends TestCase
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

    private function minimalData(): TopbarData
    {
        return new TopbarData(portalUrl: '/horde/', version: 'H6');
    }

    #[Test]
    public function renderRegistersTopbarJs(): void
    {
        $discoverer = $this->createDiscoverer([
            'topbar.js' => '/static/js/topbar.js',
            'date/date.js' => '/static/js/date/date.js',
        ]);
        $renderer = new TopbarRenderer($this->collector, $discoverer);
        $renderer->render($this->minimalData());

        self::assertContains('/static/js/topbar.js', $this->collector->getScriptUrls());
    }

    #[Test]
    public function renderRegistersDateJs(): void
    {
        $discoverer = $this->createDiscoverer([
            'topbar.js' => '/static/js/topbar.js',
            'date/date.js' => '/static/js/date/date.js',
        ]);
        $renderer = new TopbarRenderer($this->collector, $discoverer);
        $renderer->render($this->minimalData());

        self::assertContains('/static/js/date/date.js', $this->collector->getScriptUrls());
    }

    #[Test]
    public function renderSkipsUnresolvedScripts(): void
    {
        $discoverer = $this->createDiscoverer([]);
        $renderer = new TopbarRenderer($this->collector, $discoverer);
        $renderer->render($this->minimalData());

        self::assertEmpty($this->collector->getScriptUrls());
    }

    #[Test]
    public function renderRegistersFormGhostWhenSearchHasMenu(): void
    {
        $discoverer = $this->createDiscoverer([
            'topbar.js' => '/js/topbar.js',
            'date/date.js' => '/js/date/date.js',
            'form_ghost.js' => '/js/form_ghost.js',
        ]);
        $search = new TopbarSearchConfig(
            action: '/search',
            label: 'Search',
            iconUrl: '/icon.png',
            hasMenu: true,
        );
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', searchConfig: $search);
        $renderer = new TopbarRenderer($this->collector, $discoverer);
        $renderer->render($data);

        self::assertContains('/js/form_ghost.js', $this->collector->getScriptUrls());
    }

    #[Test]
    public function renderSkipsFormGhostWhenSearchHasNoMenu(): void
    {
        $discoverer = $this->createDiscoverer([
            'topbar.js' => '/js/topbar.js',
            'date/date.js' => '/js/date/date.js',
            'form_ghost.js' => '/js/form_ghost.js',
        ]);
        $search = new TopbarSearchConfig(
            action: '/search',
            label: 'Search',
            iconUrl: '/icon.png',
            hasMenu: false,
        );
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', searchConfig: $search);
        $renderer = new TopbarRenderer($this->collector, $discoverer);
        $renderer->render($data);

        self::assertNotContains('/js/form_ghost.js', $this->collector->getScriptUrls());
    }

    #[Test]
    public function renderSkipsFormGhostWhenNoSearch(): void
    {
        $discoverer = $this->createDiscoverer([
            'topbar.js' => '/js/topbar.js',
            'date/date.js' => '/js/date/date.js',
            'form_ghost.js' => '/js/form_ghost.js',
        ]);
        $renderer = new TopbarRenderer($this->collector, $discoverer);
        $renderer->render($this->minimalData());

        self::assertNotContains('/js/form_ghost.js', $this->collector->getScriptUrls());
    }
}
