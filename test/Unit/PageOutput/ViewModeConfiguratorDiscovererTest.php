<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\PageOutput;

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\ViewMode;
use Horde\Core\PageOutput\ViewModeConfigurator;
use Horde\Core\Service\PrefsService;
use Horde\Core\Session\HordeSession;
use Horde\Url\Url;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewModeConfigurator::class)]
class ViewModeConfiguratorDiscovererTest extends TestCase
{
    private function createDiscoverer(array $resolveMap): JsDiscoverer
    {
        return new class ($resolveMap) implements JsDiscoverer {
            public array $resolvedFiles = [];

            public function __construct(private readonly array $map) {}

            public function resolve(string $file, string $app = 'horde'): ?string
            {
                $this->resolvedFiles[] = $file;
                return $this->map[$file] ?? null;
            }

            public function resolveMany(array $files, string $app = 'horde'): array
            {
                $result = [];
                foreach ($files as $f) {
                    $this->resolvedFiles[] = $f;
                    $result[$f] = $this->map[$f] ?? null;
                }
                return $result;
            }
        };
    }

    private function createConfigurator(JsDiscoverer $discoverer): ViewModeConfigurator
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('getApp')->willReturn('horde');
        $registry->method('getServiceLink')->willReturn(new Url('/horde/services/ajax.php'));

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturn(false);

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        return new ViewModeConfigurator($registry, $prefs, $session, $discoverer);
    }

    #[Test]
    public function basicModeResolvesPrototypeAndHorde(): void
    {
        $discoverer = $this->createDiscoverer([
            'prototype.js' => '/static/js/prototype.js',
            'horde.js' => '/static/js/horde.js',
        ]);
        $configurator = $this->createConfigurator($discoverer);
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::BASIC);

        self::assertContains('/static/js/prototype.js', $collector->getScriptUrls());
        self::assertContains('/static/js/horde.js', $collector->getScriptUrls());
    }

    #[Test]
    public function basicModeSkipsUnresolvedFiles(): void
    {
        $discoverer = $this->createDiscoverer([
            'prototype.js' => '/js/prototype.js',
        ]);
        $configurator = $this->createConfigurator($discoverer);
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::BASIC);

        self::assertContains('/js/prototype.js', $collector->getScriptUrls());
        self::assertCount(1, $collector->getScriptUrls());
    }

    #[Test]
    public function dynamicModeResolvesFourScripts(): void
    {
        $discoverer = $this->createDiscoverer([
            'prototype.js' => '/js/prototype.js',
            'horde.js' => '/js/horde.js',
            'hordecore.js' => '/js/hordecore.js',
            'growler.js' => '/js/growler.js',
            'scriptaculous/effects.js' => '/js/scriptaculous/effects.js',
            'scriptaculous/sound.js' => '/js/scriptaculous/sound.js',
        ]);
        $configurator = $this->createConfigurator($discoverer);
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::DYNAMIC);

        $urls = $collector->getScriptUrls();
        self::assertContains('/js/hordecore.js', $urls);
        self::assertContains('/js/growler.js', $urls);
        self::assertContains('/js/scriptaculous/effects.js', $urls);
        self::assertContains('/js/scriptaculous/sound.js', $urls);
    }

    #[Test]
    public function accesskeysResolvedViaDiscoverer(): void
    {
        $discoverer = $this->createDiscoverer([
            'prototype.js' => '/js/prototype.js',
            'horde.js' => '/js/horde.js',
            'accesskeys.js' => '/js/accesskeys.js',
        ]);

        $registry = $this->createMock(Horde_Registry::class);
        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturnCallback(function ($uid, $app, $key) {
            if ($key === 'widget_accesskey') {
                return true;
            }
            return null;
        });

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');

        $configurator = new ViewModeConfigurator($registry, $prefs, $session, $discoverer);
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::BASIC);

        self::assertContains('/js/accesskeys.js', $collector->getScriptUrls());
    }

    #[Test]
    public function discovererCalledWithHordeApp(): void
    {
        $calledApps = [];
        $discoverer = new class ($calledApps) implements JsDiscoverer {
            public function __construct(private array &$apps) {}

            public function resolve(string $file, string $app = 'horde'): ?string
            {
                $this->apps[] = $app;
                return '/js/' . $file;
            }

            public function resolveMany(array $files, string $app = 'horde'): array
            {
                $this->apps[] = $app;
                return array_combine($files, array_map(fn($f) => '/js/' . $f, $files));
            }
        };

        $registry = $this->createMock(Horde_Registry::class);
        $prefs = $this->createMock(PrefsService::class);
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $configurator = new ViewModeConfigurator($registry, $prefs, $session, $discoverer);
        $collector = new AssetCollector();
        $configurator->configure($collector, ViewMode::BASIC);

        foreach ($calledApps as $app) {
            self::assertSame('horde', $app);
        }
    }
}
