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

namespace Horde\Core\Test\Unit\Topbar;

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\Topbar\TopbarData;
use Horde\Core\Topbar\TopbarMenuNode;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Core\Topbar\TopbarSearchConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopbarRenderer::class)]
class TopbarRendererTest extends TestCase
{
    private function minimalData(): TopbarData
    {
        return new TopbarData(portalUrl: '/horde/', version: 'H6');
    }

    /**
     * Build a JsDiscoverer mock that expects resolveMany() to be called once
     * (always invoked by registerScripts()). resolve() is only invoked when
     * searchConfig?->hasMenu is true; callers that do not use it should pass
     * $expectResolve = false to assert the method is never called.
     */
    private function jsDiscoverer(bool $expectResolve = false): MockObject&JsDiscoverer
    {
        $mock = $this->createMock(JsDiscoverer::class);
        $mock->expects($this->once())
            ->method('resolveMany')
            ->with(['topbar.js', 'date/date.js'], 'horde')
            ->willReturn([]);

        if ($expectResolve) {
            $mock->expects($this->once())
                ->method('resolve')
                ->with('form_ghost.js', 'horde')
                ->willReturn(null);
        } else {
            $mock->expects($this->never())->method('resolve');
        }

        return $mock;
    }

    private function makeRenderer(AssetCollector $collector, bool $expectResolve = false): TopbarRenderer
    {
        return new TopbarRenderer($collector, $this->jsDiscoverer($expectResolve));
    }

    public function testRenderContainsHordeHead(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $html = $renderer->render($this->minimalData());
        $this->assertStringContainsString('id="horde-head"', $html);
    }

    public function testRenderContainsLogoWithPortalUrl(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $html = $renderer->render($this->minimalData());
        $this->assertStringContainsString('href="/horde/"', $html);
        $this->assertStringContainsString('id="horde-logo"', $html);
    }

    public function testRenderContainsVersion(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $html = $renderer->render($this->minimalData());
        $this->assertStringContainsString('id="horde-version"', $html);
        $this->assertStringContainsString('H6', $html);
    }

    public function testRenderContainsLogoutLink(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', logoutUrl: '/horde/logout');
        $html = $renderer->render($data);

        $this->assertStringContainsString('id="horde-logout"', $html);
        $this->assertStringContainsString('href="/horde/logout"', $html);
    }

    public function testRenderContainsLoginLink(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', loginUrl: '/horde/login');
        $html = $renderer->render($data);

        $this->assertStringContainsString('id="horde-login"', $html);
        $this->assertStringContainsString('href="/horde/login"', $html);
    }

    public function testRenderNoLogoutOrLogin(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $html = $renderer->render($this->minimalData());
        $this->assertStringNotContainsString('id="horde-logout"', $html);
        $this->assertStringNotContainsString('id="horde-login"', $html);
    }

    public function testRenderMenuNodes(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $node = new TopbarMenuNode(id: 'mail', label: 'Mail', url: '/imp/', active: true);
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', menuTree: [$node]);
        $html = $renderer->render($data);

        $this->assertStringContainsString('horde-navipoint', $html);
        $this->assertStringContainsString('horde-mainnavi-active', $html);
        $this->assertStringContainsString('href="/imp/"', $html);
        $this->assertStringContainsString('Mail', $html);
    }

    public function testRenderMenuNodeWithChildren(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $child = new TopbarMenuNode(id: 'inbox', label: 'Inbox', url: '/imp/mailbox/INBOX');
        $parent = new TopbarMenuNode(id: 'mail', label: 'Mail', url: '/imp/', children: [$child]);
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', menuTree: [$parent]);
        $html = $renderer->render($data);

        $this->assertStringContainsString('&#9662;', $html);
        $this->assertStringContainsString('Inbox', $html);
        $this->assertStringContainsString('/imp/mailbox/INBOX', $html);
    }

    public function testRenderMenuNodeNoarrow(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $child = new TopbarMenuNode(id: 'sub', label: 'Sub');
        $node = new TopbarMenuNode(id: 'app', label: 'App', children: [$child], noarrow: true);
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', menuTree: [$node]);
        $html = $renderer->render($data);

        $this->assertStringNotContainsString('horde-point-arrow', $html);
    }

    public function testRenderSearchForm(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $search = new TopbarSearchConfig(
            action: '/imp/search',
            label: 'Search Mail',
            iconUrl: '/themes/search.png',
            parameters: ['page' => 'mailbox'],
        );
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', searchConfig: $search);
        $html = $renderer->render($data);

        $this->assertStringContainsString('id="horde-search"', $html);
        $this->assertStringContainsString('action="/imp/search"', $html);
        $this->assertStringContainsString('name="page"', $html);
        $this->assertStringContainsString('value="mailbox"', $html);
        $this->assertStringContainsString('src="/themes/search.png"', $html);
    }

    public function testRenderSearchFormWithMenu(): void
    {
        // hasMenu=true triggers the resolve() call for form_ghost.js.
        $renderer = $this->makeRenderer(new AssetCollector(), expectResolve: true);
        $search = new TopbarSearchConfig(
            action: '/search',
            label: 'Search',
            iconUrl: '/icon.png',
            hasMenu: true,
        );
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', searchConfig: $search);
        $html = $renderer->render($data);

        $this->assertStringContainsString('horde-search-dropdown', $html);
        $this->assertStringContainsString('horde-fake-input', $html);
    }

    public function testRenderSubbar(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $data = new TopbarData(
            portalUrl: '/horde/',
            version: 'H6',
            date: 'April 21, 2026',
            subinfo: 'admin@example.com',
        );
        $html = $renderer->render($data);

        $this->assertStringContainsString('id="horde-sub"', $html);
        $this->assertStringContainsString('id="horde-date"', $html);
        $this->assertStringContainsString('April 21, 2026', $html);
        $this->assertStringContainsString('admin@example.com', $html);
    }

    public function testRenderBodyWrappersWithSidebar(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $data = new TopbarData(
            portalUrl: '/horde/',
            version: 'H6',
            sidebarEnabled: true,
            sidebarWidth: 200,
        );
        $html = $renderer->render($data);

        $this->assertStringContainsString('id="horde-body"', $html);
        $this->assertStringContainsString('id="horde-contentwrapper"', $html);
        $this->assertStringContainsString('margin-left:200px', $html);
    }

    public function testRenderBodyWrappersNoSidebar(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $data = new TopbarData(
            portalUrl: '/horde/',
            version: 'H6',
            sidebarEnabled: false,
        );
        $html = $renderer->render($data);

        $this->assertStringContainsString('horde-no-sidebar', $html);
        $this->assertStringNotContainsString('margin-left', $html);
    }

    public function testRenderRegistersJsConfig(): void
    {
        $collector = new AssetCollector();
        $renderer = $this->makeRenderer($collector);
        $data = new TopbarData(
            portalUrl: '/horde/',
            version: 'H6',
            jsConfig: ['app' => 'horde'],
        );
        $renderer->render($data);

        $varsHtml = $collector->renderJsVarBlock();
        $this->assertStringContainsString('HordeTopbar.conf=', $varsHtml);
    }

    public function testRenderEscapesXss(): void
    {
        $renderer = $this->makeRenderer(new AssetCollector());
        $data = new TopbarData(
            portalUrl: '/horde/"><script>alert(1)</script>',
            version: '<script>',
        );
        $html = $renderer->render($data);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
