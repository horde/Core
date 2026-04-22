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

use Horde\Core\Sidebar\SidebarCollector;
use Horde\Core\Sidebar\SidebarData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SidebarCollector::class)]
class SidebarCollectorTest extends TestCase
{
    private SidebarCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new SidebarCollector();
    }

    public function testAddRowCreatesDefaultContainer(): void
    {
        $this->collector->addRow([
            'label' => 'Inbox',
            'url' => '/imp/mailbox/INBOX',
            'cssClass' => 'imp-inbox',
        ]);

        $this->assertArrayHasKey('', $this->collector->containers);
        $this->assertCount(1, $this->collector->containers['']['rows']);
    }

    public function testAddRowCreatesNamedContainer(): void
    {
        $this->collector->addRow(['label' => 'Test'], 'my-container');

        $this->assertArrayHasKey('my-container', $this->collector->containers);
        $this->assertSame('my-container', $this->collector->containers['my-container']['id']);
    }

    public function testAddRowTreeTypeBuildsLink(): void
    {
        $this->collector->addRow([
            'label' => 'Inbox',
            'url' => '/imp/mailbox/INBOX',
        ]);

        $row = $this->collector->containers['']['rows'][0];
        $this->assertStringContainsString('href="/imp/mailbox/INBOX"', $row['link']);
        $this->assertStringContainsString('Inbox', $row['link']);
        $this->assertStringContainsString('</a>', $row['link']);
    }

    public function testAddRowWithoutUrlBuildsSpan(): void
    {
        $this->collector->addRow(['label' => 'No Link']);

        $row = $this->collector->containers['']['rows'][0];
        $this->assertStringContainsString('horde-resource-none', $row['link']);
        $this->assertStringContainsString('No Link', $row['link']);
    }

    public function testAddRowEscapesLabel(): void
    {
        $this->collector->addRow([
            'label' => 'Test & <b>Bold</b>',
            'url' => '/test',
        ]);

        $row = $this->collector->containers['']['rows'][0];
        $this->assertStringContainsString('Test &amp; &lt;b&gt;Bold&lt;/b&gt;', $row['link']);
    }

    public function testAddRowPreservesPrebuiltLink(): void
    {
        $this->collector->addRow([
            'label' => 'Item',
            'link' => '<a href="/custom">Custom Link</a>',
        ]);

        $row = $this->collector->containers['']['rows'][0];
        $this->assertSame('<a href="/custom">Custom Link</a>', $row['link']);
    }

    public function testAddRowCheckboxType(): void
    {
        $this->collector->addRow([
            'label' => 'Work',
            'url' => '/nag/?tasklist=work',
            'type' => 'checkbox',
            'color' => '#ff0000',
            'selected' => true,
        ]);

        $container = $this->collector->containers[''];
        $this->assertSame('checkbox', $container['type']);

        $row = $container['rows'][0];
        $this->assertStringContainsString('background-color:#ff0000', $row['style']);
        $this->assertStringContainsString('color:#fff', $row['style']);
        $this->assertStringContainsString('horde-resource-on', $row['link']);
    }

    public function testAddRowCheckboxDefaultColor(): void
    {
        $this->collector->addRow([
            'label' => 'Default',
            'url' => '/test',
            'type' => 'checkbox',
        ]);

        $row = $this->collector->containers['']['rows'][0];
        $this->assertStringContainsString('background-color:#dddddd', $row['style']);
        $this->assertStringContainsString('color:#000', $row['style']);
    }

    public function testAddRowRadioboxType(): void
    {
        $this->collector->addRow([
            'label' => 'Cal',
            'url' => '/kronolith/',
            'type' => 'radiobox',
            'color' => '#0000ff',
        ]);

        $row = $this->collector->containers['']['rows'][0];
        $this->assertStringContainsString('horde-radiobox', $row['link']);
        $this->assertStringContainsString('horde-resource-off', $row['link']);
    }

    public function testAddRowCheckboxWithEditUrl(): void
    {
        $this->collector->addRow([
            'label' => 'Work',
            'url' => '/nag/',
            'type' => 'checkbox',
            'color' => '#ff0000',
            'edit' => '/nag/edit/work',
        ]);

        $row = $this->collector->containers['']['rows'][0];
        $this->assertArrayHasKey('editLink', $row);
        $this->assertStringContainsString('horde-resource-edit-fff', $row['editLink']);
        $this->assertStringContainsString('&#9658;', $row['editLink']);
    }

    public function testAddRowWithOnclickAndTarget(): void
    {
        $this->collector->addRow([
            'label' => 'External',
            'url' => '/external',
            'onclick' => 'return confirm("sure?")',
            'target' => '_blank',
        ]);

        $row = $this->collector->containers['']['rows'][0];
        $this->assertStringContainsString('onclick="return confirm(&quot;sure?&quot;)"', $row['link']);
        $this->assertStringContainsString('target="_blank"', $row['link']);
    }

    public function testBrightnessCalculationDarkBackground(): void
    {
        $this->assertSame('fff', SidebarCollector::calculateForeground('#000000'));
        $this->assertSame('fff', SidebarCollector::calculateForeground('#ff0000'));
        $this->assertSame('fff', SidebarCollector::calculateForeground('#0000ff'));
    }

    public function testBrightnessCalculationLightBackground(): void
    {
        $this->assertSame('000', SidebarCollector::calculateForeground('#ffffff'));
        $this->assertSame('000', SidebarCollector::calculateForeground('#dddddd'));
        $this->assertSame('000', SidebarCollector::calculateForeground('#ffff00'));
    }

    public function testBrightnessCalculationShortHex(): void
    {
        $this->assertSame('fff', SidebarCollector::calculateForeground('#000'));
        $this->assertSame('000', SidebarCollector::calculateForeground('#fff'));
    }

    public function testAddNewButtonWithString(): void
    {
        $this->collector->addNewButton('New Task', '<a href="/nag/new">');

        $this->assertSame('<a href="/nag/new">', $this->collector->newLink);
        $this->assertSame('New Task', $this->collector->newText);
    }

    public function testToSidebarDataEmpty(): void
    {
        $data = $this->collector->toSidebarData();

        $this->assertInstanceOf(SidebarData::class, $data);
        $this->assertNull($data->newButton);
        $this->assertSame([], $data->containers);
        $this->assertSame(150, $data->width);
        $this->assertFalse($data->isRtl);
    }

    public function testToSidebarDataWithButton(): void
    {
        $this->collector->newLink = '<a href="/new">';
        $this->collector->newText = 'New Item';
        $this->collector->newExtra = '<span>extra</span>';

        $data = $this->collector->toSidebarData(200, true);

        $this->assertNotNull($data->newButton);
        $this->assertSame('New Item', $data->newButton->label);
        $this->assertSame('<a href="/new">', $data->newButton->url);
        $this->assertSame('<span>extra</span>', $data->newButton->extra);
        $this->assertSame(200, $data->width);
        $this->assertTrue($data->isRtl);
    }

    public function testToSidebarDataConvertsContainers(): void
    {
        $this->collector->addRow([
            'label' => 'Item 1',
            'url' => '/item1',
            'cssClass' => 'icon-item',
            'selected' => true,
        ]);
        $this->collector->addRow([
            'label' => 'Item 2',
            'url' => '/item2',
        ]);

        $data = $this->collector->toSidebarData();

        $this->assertCount(1, $data->containers);
        $this->assertCount(2, $data->containers[0]->rows);
        $this->assertSame('Item 1', $data->containers[0]->rows[0]->label);
        $this->assertTrue($data->containers[0]->rows[0]->selected);
        $this->assertSame('icon-item', $data->containers[0]->rows[0]->cssClass);
    }

    public function testToSidebarDataWithHeader(): void
    {
        $this->collector->containers['tasks'] = [
            'id' => 'tasks',
            'header' => [
                'id' => 'tasks-toggle',
                'label' => 'Task Lists',
                'collapsed' => false,
                'add' => [
                    'url' => '/nag/create',
                    'label' => 'Create list',
                ],
            ],
            'rows' => [],
        ];

        $data = $this->collector->toSidebarData();

        $this->assertNotNull($data->containers[0]->header);
        $this->assertSame('tasks-toggle', $data->containers[0]->header->id);
        $this->assertSame('Task Lists', $data->containers[0]->header->label);
        $this->assertFalse($data->containers[0]->header->collapsed);
        $this->assertSame('/nag/create', $data->containers[0]->header->addUrl);
        $this->assertSame('Create list', $data->containers[0]->header->addLabel);
    }

    public function testToSidebarDataCookieOverridesCollapsed(): void
    {
        $this->collector->containers['test'] = [
            'header' => [
                'id' => 'test-toggle',
                'label' => 'Test',
                'collapsed' => false,
            ],
            'rows' => [],
        ];

        $data = $this->collector->toSidebarData(150, false, [
            'horde_sidebar_c_test-toggle' => '1',
        ]);

        $this->assertTrue($data->containers[0]->header->collapsed);
    }

    public function testToSidebarDataCookieOverridesExpanded(): void
    {
        $this->collector->containers['test'] = [
            'header' => [
                'id' => 'test-toggle',
                'label' => 'Test',
                'collapsed' => true,
            ],
            'rows' => [],
        ];

        $data = $this->collector->toSidebarData(150, false, [
            'horde_sidebar_c_test-toggle' => '',
        ]);

        $this->assertFalse($data->containers[0]->header->collapsed);
    }

    public function testToSidebarDataCheckboxRows(): void
    {
        $this->collector->addRow([
            'label' => 'Work',
            'url' => '/nag/?tasklist=work',
            'type' => 'checkbox',
            'color' => '#ff0000',
        ]);

        $data = $this->collector->toSidebarData();

        $row = $data->containers[0]->rows[0];
        $this->assertSame('checkbox', $row->type);
        $this->assertSame('#ff0000', $row->color);
        $this->assertSame('#fff', $row->foregroundColor);
        $this->assertStringContainsString('background-color', $row->style);
    }

    public function testMultipleContainers(): void
    {
        $this->collector->addRow(['label' => 'A'], 'first');
        $this->collector->addRow(['label' => 'B'], 'second');

        $data = $this->collector->toSidebarData();

        $this->assertCount(2, $data->containers);
        $this->assertSame('first', $data->containers[0]->id);
        $this->assertSame('second', $data->containers[1]->id);
    }
}
