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

use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\Topbar\TopbarData;
use Horde\Core\Topbar\TopbarMenuNode;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Core\Topbar\TopbarSearchConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopbarRenderer::class)]
class TopbarRendererTest extends TestCase
{
    private AssetCollector $collector;
    private TopbarRenderer $renderer;

    protected function setUp(): void
    {
        $this->collector = new AssetCollector();
        $this->renderer = new TopbarRenderer($this->collector);
    }

    private function minimalData(): TopbarData
    {
        return new TopbarData(portalUrl: '/horde/', version: 'H6');
    }

    public function testRenderContainsHordeHead(): void
    {
        $html = $this->renderer->render($this->minimalData());
        $this->assertStringContainsString('id="horde-head"', $html);
    }

    public function testRenderContainsLogoWithPortalUrl(): void
    {
        $html = $this->renderer->render($this->minimalData());
        $this->assertStringContainsString('href="/horde/"', $html);
        $this->assertStringContainsString('id="horde-logo"', $html);
    }

    public function testRenderContainsVersion(): void
    {
        $html = $this->renderer->render($this->minimalData());
        $this->assertStringContainsString('id="horde-version"', $html);
        $this->assertStringContainsString('H6', $html);
    }

    public function testRenderContainsLogoutLink(): void
    {
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', logoutUrl: '/horde/logout');
        $html = $this->renderer->render($data);

        $this->assertStringContainsString('id="horde-logout"', $html);
        $this->assertStringContainsString('href="/horde/logout"', $html);
    }

    public function testRenderContainsLoginLink(): void
    {
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', loginUrl: '/horde/login');
        $html = $this->renderer->render($data);

        $this->assertStringContainsString('id="horde-login"', $html);
        $this->assertStringContainsString('href="/horde/login"', $html);
    }

    public function testRenderNoLogoutOrLogin(): void
    {
        $html = $this->renderer->render($this->minimalData());
        $this->assertStringNotContainsString('id="horde-logout"', $html);
        $this->assertStringNotContainsString('id="horde-login"', $html);
    }

    public function testRenderMenuNodes(): void
    {
        $node = new TopbarMenuNode(id: 'mail', label: 'Mail', url: '/imp/', active: true);
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', menuTree: [$node]);
        $html = $this->renderer->render($data);

        $this->assertStringContainsString('horde-navipoint', $html);
        $this->assertStringContainsString('horde-mainnavi-active', $html);
        $this->assertStringContainsString('href="/imp/"', $html);
        $this->assertStringContainsString('Mail', $html);
    }

    public function testRenderMenuNodeWithChildren(): void
    {
        $child = new TopbarMenuNode(id: 'inbox', label: 'Inbox', url: '/imp/mailbox/INBOX');
        $parent = new TopbarMenuNode(id: 'mail', label: 'Mail', url: '/imp/', children: [$child]);
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', menuTree: [$parent]);
        $html = $this->renderer->render($data);

        $this->assertStringContainsString('&#9662;', $html);
        $this->assertStringContainsString('Inbox', $html);
        $this->assertStringContainsString('/imp/mailbox/INBOX', $html);
    }

    public function testRenderMenuNodeNoarrow(): void
    {
        $child = new TopbarMenuNode(id: 'sub', label: 'Sub');
        $node = new TopbarMenuNode(id: 'app', label: 'App', children: [$child], noarrow: true);
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', menuTree: [$node]);
        $html = $this->renderer->render($data);

        $this->assertStringNotContainsString('horde-point-arrow', $html);
    }

    public function testRenderSearchForm(): void
    {
        $search = new TopbarSearchConfig(
            action: '/imp/search',
            label: 'Search Mail',
            iconUrl: '/themes/search.png',
            parameters: ['page' => 'mailbox'],
        );
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', searchConfig: $search);
        $html = $this->renderer->render($data);

        $this->assertStringContainsString('id="horde-search"', $html);
        $this->assertStringContainsString('action="/imp/search"', $html);
        $this->assertStringContainsString('name="page"', $html);
        $this->assertStringContainsString('value="mailbox"', $html);
        $this->assertStringContainsString('src="/themes/search.png"', $html);
    }

    public function testRenderSearchFormWithMenu(): void
    {
        $search = new TopbarSearchConfig(
            action: '/search',
            label: 'Search',
            iconUrl: '/icon.png',
            hasMenu: true,
        );
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6', searchConfig: $search);
        $html = $this->renderer->render($data);

        $this->assertStringContainsString('horde-search-dropdown', $html);
        $this->assertStringContainsString('horde-fake-input', $html);
    }

    public function testRenderSubbar(): void
    {
        $data = new TopbarData(
            portalUrl: '/horde/',
            version: 'H6',
            date: 'April 21, 2026',
            subinfo: 'admin@example.com',
        );
        $html = $this->renderer->render($data);

        $this->assertStringContainsString('id="horde-sub"', $html);
        $this->assertStringContainsString('id="horde-date"', $html);
        $this->assertStringContainsString('April 21, 2026', $html);
        $this->assertStringContainsString('admin@example.com', $html);
    }

    public function testRenderBodyWrappersWithSidebar(): void
    {
        $data = new TopbarData(
            portalUrl: '/horde/',
            version: 'H6',
            sidebarEnabled: true,
            sidebarWidth: 200,
        );
        $html = $this->renderer->render($data);

        $this->assertStringContainsString('id="horde-body"', $html);
        $this->assertStringContainsString('id="horde-contentwrapper"', $html);
        $this->assertStringContainsString('margin-left:200px', $html);
    }

    public function testRenderBodyWrappersNoSidebar(): void
    {
        $data = new TopbarData(
            portalUrl: '/horde/',
            version: 'H6',
            sidebarEnabled: false,
        );
        $html = $this->renderer->render($data);

        $this->assertStringContainsString('horde-no-sidebar', $html);
        $this->assertStringNotContainsString('margin-left', $html);
    }

    public function testRenderRegistersJsConfig(): void
    {
        $data = new TopbarData(
            portalUrl: '/horde/',
            version: 'H6',
            jsConfig: ['app' => 'horde'],
        );
        $this->renderer->render($data);

        $varsHtml = $this->collector->renderJsVarBlock();
        $this->assertStringContainsString('HordeTopbar.conf=', $varsHtml);
    }

    public function testRenderEscapesXss(): void
    {
        $data = new TopbarData(
            portalUrl: '/horde/"><script>alert(1)</script>',
            version: '<script>',
        );
        $html = $this->renderer->render($data);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
