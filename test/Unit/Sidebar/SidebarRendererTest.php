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

namespace Horde\Core\Test\Unit\Sidebar;

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\Sidebar\SidebarButton;
use Horde\Core\Sidebar\SidebarContainer;
use Horde\Core\Sidebar\SidebarData;
use Horde\Core\Sidebar\SidebarHeader;
use Horde\Core\Sidebar\SidebarRenderer;
use Horde\Core\Sidebar\SidebarRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SidebarRenderer::class)]
class SidebarRendererTest extends TestCase
{
    /**
     * Build a renderer with a strict JsDiscoverer mock.
     *
     * The renderer always calls resolve('sidebar.js', 'horde'); it additionally
     * calls resolve('scriptaculous/effects.js', 'horde') iff at least one
     * container has a header. resolveMany() is never used by the renderer.
     */
    private function makeRenderer(bool $expectsEffectsScript = false): SidebarRenderer
    {
        $collector = new AssetCollector();
        $jsDiscoverer = $this->createMock(JsDiscoverer::class);
        $jsDiscoverer->expects(self::exactly($expectsEffectsScript ? 2 : 1))
            ->method('resolve')
            ->willReturnMap([
                ['sidebar.js', 'horde', null],
                ['scriptaculous/effects.js', 'horde', null],
            ]);
        $jsDiscoverer->expects(self::never())->method('resolveMany');

        return new SidebarRenderer($collector, $jsDiscoverer);
    }

    public function testRenderEmptySidebar(): void
    {
        $renderer = $this->makeRenderer();
        $data = new SidebarData();
        $html = $renderer->render($data);

        $this->assertStringContainsString('id="horde-sidebar"', $html);
        $this->assertStringContainsString('width:150px', $html);
        $this->assertStringContainsString('id="horde-slideleft"', $html);
    }

    public function testRenderClosesContentDivs(): void
    {
        $renderer = $this->makeRenderer();
        $data = new SidebarData();
        $html = $renderer->render($data);

        $this->assertStringStartsWith('</div>' . "\n" . '</div>', $html);
    }

    public function testRenderCustomWidth(): void
    {
        $renderer = $this->makeRenderer();
        $data = new SidebarData(width: 250);
        $html = $renderer->render($data);

        $this->assertStringContainsString('width:250px', $html);
    }

    public function testRenderRtlSlidebarPosition(): void
    {
        $renderer = $this->makeRenderer();
        $data = new SidebarData(isRtl: true);
        $html = $renderer->render($data);

        $this->assertStringContainsString('right:150px', $html);
    }

    public function testRenderLtrSlidebarPosition(): void
    {
        $renderer = $this->makeRenderer();
        $data = new SidebarData(isRtl: false);
        $html = $renderer->render($data);

        $this->assertStringContainsString('left:150px', $html);
    }

    public function testRenderNewButton(): void
    {
        $renderer = $this->makeRenderer();
        $button = new SidebarButton(
            label: 'New Task',
            url: '<a href="/nag/task/new">',
        );
        $data = new SidebarData(newButton: $button);
        $html = $renderer->render($data);

        $this->assertStringContainsString('class="horde-new"', $html);
        $this->assertStringContainsString('horde-new-link', $html);
        $this->assertStringContainsString('New Task', $html);
        $this->assertStringContainsString('<a href="/nag/task/new">', $html);
    }

    public function testRenderNewButtonWithExtra(): void
    {
        $renderer = $this->makeRenderer();
        $button = new SidebarButton(
            label: 'New',
            url: '<a href="/new">',
            extra: '<span class="split"></span>',
        );
        $data = new SidebarData(newButton: $button);
        $html = $renderer->render($data);

        $this->assertStringContainsString('horde-new-extra', $html);
        $this->assertStringContainsString('horde-new-split', $html);
    }

    public function testRenderTreeContainer(): void
    {
        $renderer = $this->makeRenderer(expectsEffectsScript: true);
        $row = new SidebarRow(
            label: 'Inbox',
            selected: true,
            cssClass: 'imp-inbox',
            linkHtml: '<a href="/imp/mailbox/INBOX">Inbox</a>',
        );
        $header = new SidebarHeader(id: 'mail-toggle', label: 'Mail');
        $container = new SidebarContainer(
            id: 'mail-container',
            header: $header,
            rows: [$row],
            type: 'tree',
        );
        $data = new SidebarData(containers: [$container]);
        $html = $renderer->render($data);

        $this->assertStringContainsString('horde-subnavi-active', $html);
        $this->assertStringContainsString('imp-inbox', $html);
        $this->assertStringContainsString('Inbox</a>', $html);
        $this->assertStringContainsString('id="mail-toggle"', $html);
        $this->assertStringContainsString('horde-collapse', $html);
    }

    public function testRenderCheckboxContainer(): void
    {
        $renderer = $this->makeRenderer();
        $row = new SidebarRow(
            label: 'Work',
            type: 'checkbox',
            style: 'background-color:#ff0000;color:#ffffff',
            linkHtml: '<a href="/cal?cal=work">Work</a>',
            editLinkHtml: '<a href="/cal/edit/work" class="horde-resource-edit-link">Edit</a>',
        );
        $container = new SidebarContainer(
            rows: [$row],
            type: 'checkbox',
        );
        $data = new SidebarData(containers: [$container]);
        $html = $renderer->render($data);

        $this->assertStringContainsString('horde-resources', $html);
        $this->assertStringContainsString('horde-resource-link', $html);
        $this->assertStringContainsString('horde-resource-edit-link', $html);
    }

    public function testRenderCollapsedContainer(): void
    {
        $renderer = $this->makeRenderer(expectsEffectsScript: true);
        $header = new SidebarHeader(id: 'collapsed', label: 'Hidden', collapsed: true);
        $container = new SidebarContainer(header: $header);
        $data = new SidebarData(containers: [$container]);
        $html = $renderer->render($data);

        $this->assertStringContainsString('horde-expand', $html);
        $this->assertStringContainsString('style="display:none"', $html);
    }

    public function testRenderHeaderWithAddLink(): void
    {
        $renderer = $this->makeRenderer(expectsEffectsScript: true);
        $header = new SidebarHeader(
            id: 'tasks',
            label: 'Task Lists',
            addUrl: '/nag/tasklists/create',
            addLabel: 'Create task list',
        );
        $container = new SidebarContainer(header: $header);
        $data = new SidebarData(containers: [$container]);
        $html = $renderer->render($data);

        $this->assertStringContainsString('class="horde-add"', $html);
        $this->assertStringContainsString('href="/nag/tasklists/create"', $html);
        $this->assertStringContainsString('title="Create task list"', $html);
    }

    public function testRenderContainerSplitBetweenContainers(): void
    {
        $renderer = $this->makeRenderer();
        $c1 = new SidebarContainer(id: 'c1');
        $c2 = new SidebarContainer(id: 'c2');
        $data = new SidebarData(containers: [$c1, $c2]);
        $html = $renderer->render($data);

        $this->assertStringContainsString('horde-sidebar-split', $html);
    }

    public function testRenderRawContent(): void
    {
        $renderer = $this->makeRenderer();
        $data = new SidebarData(content: '<div class="custom">Custom sidebar</div>');
        $html = $renderer->render($data);

        $this->assertStringContainsString('<div class="custom">Custom sidebar</div>', $html);
    }

    public function testRenderContainerRawContent(): void
    {
        $renderer = $this->makeRenderer();
        $container = new SidebarContainer(content: '<p>Raw content</p>');
        $data = new SidebarData(containers: [$container]);
        $html = $renderer->render($data);

        $this->assertStringContainsString('<p>Raw content</p>', $html);
    }

    public function testRenderEmptyContainerShowsNoItems(): void
    {
        $renderer = $this->makeRenderer();
        $container = new SidebarContainer();
        $data = new SidebarData(containers: [$container]);
        $html = $renderer->render($data);

        $this->assertStringContainsString('No items to display', $html);
    }
}
