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

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\ViewMode;
use Horde\Core\PageOutput\ViewModeConfigurator;
use Horde\Core\Service\PrefsService;
use Horde\Core\Session\HordeSession;
use Horde\Token\GeneratedToken;
use Horde\Token\Token;
use Horde\Url\Url;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewModeConfigurator::class)]
class ViewModeConfiguratorTest extends TestCase
{
    /**
     * JsDiscoverer mock that mirrors filename-to-URL mapping. resolveMany is
     * the canonical "look up scripts" call configure() always uses; tests
     * that exercise the access-keys path also drive resolve().
     */
    private function createJsDiscovererMock(): MockObject
    {
        $jsDiscoverer = $this->createMock(JsDiscoverer::class);
        $jsDiscoverer->expects($this->atLeastOnce())
            ->method('resolveMany')
            ->willReturnCallback(
                fn(array $files) => array_combine(
                    $files,
                    array_map(fn($f) => '/js/' . $f, $files)
                )
            );
        $jsDiscoverer->method('resolve')->willReturnCallback(
            fn(string $file) => '/js/' . $file
        );

        return $jsDiscoverer;
    }

    private function createTokenServiceMock(string $token = 'token123'): MockObject
    {
        $tokenService = $this->createMock(Token::class);
        $tokenService->expects($this->any())
            ->method('generate')
            ->willReturn(new GeneratedToken($token, time()));

        return $tokenService;
    }

    public function testBasicModeAddsPrototypeAndHorde(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->never())->method('getApp');

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->once())->method('getAuthId')->willReturn(null);
        // Anonymous user: prefs lookup never fires.
        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->never())->method('getValue');

        $jsDiscoverer = $this->createJsDiscovererMock();

        $configurator = new ViewModeConfigurator(
            $registry,
            $prefs,
            $session,
            $jsDiscoverer,
            $this->createTokenServiceMock(),
        );
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::BASIC);

        $urls = $collector->getScriptUrls();
        $this->assertContains('/js/prototype.js', $urls);
        $this->assertContains('/js/horde.js', $urls);
    }

    public function testBasicModeAddsAccessKeysWhenPrefEnabled(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->never())->method('getApp');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('testuser', 'horde', 'widget_accesskey')
            ->willReturn(true);

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->once())->method('getAuthId')->willReturn('testuser');

        $configurator = new ViewModeConfigurator(
            $registry,
            $prefs,
            $session,
            $this->createJsDiscovererMock(),
            $this->createTokenServiceMock(),
        );
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::BASIC);

        $this->assertContains('/js/accesskeys.js', $collector->getScriptUrls());
    }

    public function testBasicModeSkipsAccessKeysWhenPrefDisabled(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->never())->method('getApp');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('testuser', 'horde', 'widget_accesskey')
            ->willReturn(false);

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->once())->method('getAuthId')->willReturn('testuser');

        $configurator = new ViewModeConfigurator(
            $registry,
            $prefs,
            $session,
            $this->createJsDiscovererMock(),
            $this->createTokenServiceMock(),
        );
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::BASIC);

        $this->assertNotContains('/js/accesskeys.js', $collector->getScriptUrls());
    }

    public function testDynamicModeAddsAdditionalScripts(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())->method('getApp')->willReturn('horde');
        $registry->expects($this->atLeastOnce())
            ->method('getServiceLink')
            ->willReturn(new Url('/horde/services/ajax.php'));

        $session = $this->createMock(HordeSession::class);
        // getAuthId fires for BASIC's accesskey check AND for DYNAMIC's uid lookup.
        $session->expects($this->exactly(2))->method('getAuthId')->willReturn('testuser');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('testuser', 'horde', 'widget_accesskey')
            ->willReturn(false);

        $configurator = new ViewModeConfigurator(
            $registry,
            $prefs,
            $session,
            $this->createJsDiscovererMock(),
            $this->createTokenServiceMock(),
        );
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
        $registry->expects($this->once())->method('getApp')->willReturn('horde');
        $registry->expects($this->atLeastOnce())
            ->method('getServiceLink')
            ->willReturn(new Url('/horde/services/ajax.php'));

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->exactly(2))->method('getAuthId')->willReturn('testuser');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->once())
            ->method('getValue')
            ->with('testuser', 'horde', 'widget_accesskey')
            ->willReturn(false);

        $configurator = new ViewModeConfigurator(
            $registry,
            $prefs,
            $session,
            $this->createJsDiscovererMock(),
            $this->createTokenServiceMock('mytoken'),
        );
        $collector = new AssetCollector();

        $configurator->configure($collector, ViewMode::DYNAMIC);

        $jsBlock = $collector->renderJsVarBlock();
        $this->assertStringContainsString('HordeCore.conf=', $jsBlock);
        $this->assertStringContainsString('HordeCore.text=', $jsBlock);
    }
}
