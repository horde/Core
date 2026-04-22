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

use Horde\Core\Sidebar\SidebarButton;
use Horde\Core\Sidebar\SidebarContainer;
use Horde\Core\Sidebar\SidebarData;
use Horde\Core\Sidebar\SidebarHeader;
use Horde\Core\Sidebar\SidebarRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SidebarButton::class)]
#[CoversClass(SidebarHeader::class)]
#[CoversClass(SidebarRow::class)]
#[CoversClass(SidebarContainer::class)]
#[CoversClass(SidebarData::class)]
class SidebarValueObjectsTest extends TestCase
{
    public function testButtonRequiredOnly(): void
    {
        $button = new SidebarButton(
            label: '<span class="horde-ak">N</span>ew Task',
            url: '<a href="/nag/task/new">',
        );

        $this->assertSame('<span class="horde-ak">N</span>ew Task', $button->label);
        $this->assertSame('<a href="/nag/task/new">', $button->url);
        $this->assertNull($button->extra);
    }

    public function testButtonWithExtra(): void
    {
        $button = new SidebarButton(
            label: 'New',
            url: '<a href="/new">',
            extra: '<span class="horde-sidebar-split"></span>',
        );

        $this->assertSame('<span class="horde-sidebar-split"></span>', $button->extra);
    }

    public function testHeaderRequiredOnly(): void
    {
        $header = new SidebarHeader(id: 'nag-toggle', label: 'My Tasks');

        $this->assertSame('nag-toggle', $header->id);
        $this->assertSame('My Tasks', $header->label);
        $this->assertFalse($header->collapsed);
        $this->assertNull($header->addUrl);
        $this->assertNull($header->addLabel);
    }

    public function testHeaderAllProperties(): void
    {
        $header = new SidebarHeader(
            id: 'nag-toggle',
            label: 'Task Lists',
            collapsed: true,
            addUrl: '/nag/tasklists/create',
            addLabel: 'Create new task list',
        );

        $this->assertTrue($header->collapsed);
        $this->assertSame('/nag/tasklists/create', $header->addUrl);
        $this->assertSame('Create new task list', $header->addLabel);
    }

    public function testRowRequiredOnly(): void
    {
        $row = new SidebarRow(label: 'Inbox');

        $this->assertSame('Inbox', $row->label);
        $this->assertSame('', $row->url);
        $this->assertFalse($row->selected);
        $this->assertSame('tree', $row->type);
        $this->assertNull($row->cssClass);
        $this->assertNull($row->id);
        $this->assertNull($row->color);
        $this->assertNull($row->foregroundColor);
        $this->assertNull($row->editUrl);
        $this->assertNull($row->onclick);
        $this->assertNull($row->target);
        $this->assertSame('', $row->linkHtml);
        $this->assertNull($row->editLinkHtml);
        $this->assertNull($row->style);
    }

    public function testRowTreeType(): void
    {
        $row = new SidebarRow(
            label: 'Inbox',
            url: '/imp/mailbox/INBOX',
            selected: true,
            cssClass: 'imp-sidebar-inbox',
            id: 'imp-inbox-link',
            linkHtml: '<a href="/imp/mailbox/INBOX" id="imp-inbox-link">Inbox</a>',
        );

        $this->assertSame('/imp/mailbox/INBOX', $row->url);
        $this->assertTrue($row->selected);
        $this->assertSame('imp-sidebar-inbox', $row->cssClass);
        $this->assertSame('imp-inbox-link', $row->id);
    }

    public function testRowCheckboxType(): void
    {
        $row = new SidebarRow(
            label: 'Work',
            url: '/nag/?tasklist=work',
            type: 'checkbox',
            color: '#ff0000',
            foregroundColor: '#ffffff',
            editUrl: '/nag/tasklists/edit/work',
            style: 'background-color:#ff0000;color:#ffffff',
        );

        $this->assertSame('checkbox', $row->type);
        $this->assertSame('#ff0000', $row->color);
        $this->assertSame('#ffffff', $row->foregroundColor);
        $this->assertSame('/nag/tasklists/edit/work', $row->editUrl);
    }

    public function testContainerDefaults(): void
    {
        $container = new SidebarContainer();

        $this->assertNull($container->id);
        $this->assertNull($container->header);
        $this->assertSame([], $container->rows);
        $this->assertSame('tree', $container->type);
        $this->assertNull($container->content);
    }

    public function testContainerWithRowsAndHeader(): void
    {
        $header = new SidebarHeader(id: 'nav-toggle', label: 'Navigation');
        $row1 = new SidebarRow(label: 'Item 1');
        $row2 = new SidebarRow(label: 'Item 2');

        $container = new SidebarContainer(
            id: 'nav-container',
            header: $header,
            rows: [$row1, $row2],
            type: 'checkbox',
        );

        $this->assertSame('nav-container', $container->id);
        $this->assertSame($header, $container->header);
        $this->assertCount(2, $container->rows);
        $this->assertSame('checkbox', $container->type);
    }

    public function testContainerWithRawContent(): void
    {
        $container = new SidebarContainer(
            content: '<div class="custom-sidebar-content">Custom</div>',
        );

        $this->assertSame('<div class="custom-sidebar-content">Custom</div>', $container->content);
    }

    public function testDataDefaults(): void
    {
        $data = new SidebarData();

        $this->assertNull($data->newButton);
        $this->assertSame([], $data->containers);
        $this->assertSame(150, $data->width);
        $this->assertFalse($data->isRtl);
        $this->assertNull($data->content);
    }

    public function testDataAllProperties(): void
    {
        $button = new SidebarButton(label: 'New', url: '<a href="/new">');
        $container = new SidebarContainer(id: 'c1');

        $data = new SidebarData(
            newButton: $button,
            containers: [$container],
            width: 200,
            isRtl: true,
            content: '<div>Fallback</div>',
        );

        $this->assertSame($button, $data->newButton);
        $this->assertCount(1, $data->containers);
        $this->assertSame(200, $data->width);
        $this->assertTrue($data->isRtl);
        $this->assertSame('<div>Fallback</div>', $data->content);
    }

    public function testDataWithMultipleContainers(): void
    {
        $header1 = new SidebarHeader(id: 'toggle-1', label: 'Section 1');
        $header2 = new SidebarHeader(id: 'toggle-2', label: 'Section 2', collapsed: true);
        $row = new SidebarRow(label: 'Item', url: '/item');

        $c1 = new SidebarContainer(id: 'section-1', header: $header1, rows: [$row]);
        $c2 = new SidebarContainer(id: 'section-2', header: $header2, type: 'radiobox');

        $data = new SidebarData(containers: [$c1, $c2]);

        $this->assertCount(2, $data->containers);
        $this->assertSame('section-1', $data->containers[0]->id);
        $this->assertSame('section-2', $data->containers[1]->id);
        $this->assertTrue($data->containers[1]->header->collapsed);
    }
}
