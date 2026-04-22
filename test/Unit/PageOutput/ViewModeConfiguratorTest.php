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

namespace Horde\Core\Test\Unit\PageOutput;

use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\ViewMode;
use Horde\Core\PageOutput\ViewModeConfigurator;
use Horde\Core\Service\PrefsService;
use Horde\Core\Session\HordeSession;
use Horde\Url\Url;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewModeConfigurator::class)]
class ViewModeConfiguratorTest extends TestCase
{
    private function createConfigurator(
        ?Horde_Registry $registry = null,
        ?PrefsService $prefs = null,
        ?HordeSession $session = null,
    ): ViewModeConfigurator {
        $registry ??= $this->createMock(Horde_Registry::class);
        $prefs ??= $this->createMock(PrefsService::class);
        $session ??= $this->createMock(HordeSession::class);

        return new ViewModeConfigurator($registry, $prefs, $session);
    }

    public function testBasicModeAddsPrototypeAndHorde(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('get')->willReturn('/js');

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $configurator = $this->createConfigurator(registry: $registry, session: $session);
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::BASIC);

        $urls = $collector->getScriptUrls();
        $this->assertContains('/js/prototype.js', $urls);
        $this->assertContains('/js/horde.js', $urls);
    }

    public function testBasicModeAddsAccessKeysWhenPrefEnabled(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('get')->willReturn('/js');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')
            ->with('testuser', 'horde', 'widget_accesskey')
            ->willReturn(true);

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');

        $configurator = $this->createConfigurator(registry: $registry, prefs: $prefs, session: $session);
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::BASIC);

        $this->assertContains('/js/accesskeys.js', $collector->getScriptUrls());
    }

    public function testBasicModeSkipsAccessKeysWhenPrefDisabled(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('get')->willReturn('/js');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')
            ->with('testuser', 'horde', 'widget_accesskey')
            ->willReturn(false);

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');

        $configurator = $this->createConfigurator(registry: $registry, prefs: $prefs, session: $session);
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::BASIC);

        $this->assertNotContains('/js/accesskeys.js', $collector->getScriptUrls());
    }

    public function testDynamicModeAddsAdditionalScripts(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('get')->willReturn('/js');
        $registry->method('getApp')->willReturn('horde');
        $registry->method('getServiceLink')->willReturn(new Url('/horde/services/ajax.php'));

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');
        $session->method('getScoped')->willReturn('token123');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturn(false);

        $configurator = $this->createConfigurator(registry: $registry, prefs: $prefs, session: $session);
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::DYNAMIC);

        $urls = $collector->getScriptUrls();
        $this->assertContains('/js/hordecore.js', $urls);
        $this->assertContains('/js/growler.js', $urls);
        $this->assertContains('/js/scriptaculous/effects.js', $urls);
        $this->assertContains('/js/scriptaculous/sound.js', $urls);
    }

    public function testDynamicModeRegistersHordeCoreJsVars(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('get')->willReturn('/js');
        $registry->method('getApp')->willReturn('horde');
        $registry->method('getServiceLink')->willReturn(new Url('/horde/services/ajax.php'));

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');
        $session->method('getScoped')->willReturn('mytoken');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturn(false);

        $configurator = $this->createConfigurator(registry: $registry, prefs: $prefs, session: $session);
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::DYNAMIC);

        $jsBlock = $collector->renderJsVarBlock();
        $this->assertStringContainsString('HordeCore.conf=', $jsBlock);
        $this->assertStringContainsString('HordeCore.text=', $jsBlock);
    }
}
