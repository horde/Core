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

use Horde\Core\Topbar\TopbarData;
use Horde\Core\Topbar\TopbarMenuNode;
use Horde\Core\Topbar\TopbarSearchConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopbarMenuNode::class)]
#[CoversClass(TopbarSearchConfig::class)]
#[CoversClass(TopbarData::class)]
class TopbarValueObjectsTest extends TestCase
{
    public function testMenuNodeRequiredOnly(): void
    {
        $node = new TopbarMenuNode(id: 'mail', label: 'Mail');

        $this->assertSame('mail', $node->id);
        $this->assertSame('Mail', $node->label);
        $this->assertNull($node->url);
        $this->assertNull($node->iconClass);
        $this->assertNull($node->target);
        $this->assertNull($node->onclick);
        $this->assertFalse($node->active);
        $this->assertSame([], $node->children);
        $this->assertFalse($node->noarrow);
    }

    public function testMenuNodeAllProperties(): void
    {
        $child = new TopbarMenuNode(id: 'inbox', label: 'Inbox', url: '/imp/mailbox/INBOX');
        $node = new TopbarMenuNode(
            id: 'mail',
            label: 'Mail',
            url: '/imp/',
            iconClass: 'horde-imp',
            target: '_blank',
            onclick: 'return false;',
            active: true,
            children: [$child],
            noarrow: true,
        );

        $this->assertSame('/imp/', $node->url);
        $this->assertSame('horde-imp', $node->iconClass);
        $this->assertSame('_blank', $node->target);
        $this->assertSame('return false;', $node->onclick);
        $this->assertTrue($node->active);
        $this->assertCount(1, $node->children);
        $this->assertSame('inbox', $node->children[0]->id);
        $this->assertTrue($node->noarrow);
    }

    public function testSearchConfigRequiredOnly(): void
    {
        $config = new TopbarSearchConfig(
            action: '/imp/search',
            label: 'Search Mail',
            iconUrl: '/themes/graphics/search.png',
        );

        $this->assertSame('/imp/search', $config->action);
        $this->assertSame('Search Mail', $config->label);
        $this->assertSame('/themes/graphics/search.png', $config->iconUrl);
        $this->assertSame([], $config->parameters);
        $this->assertFalse($config->hasMenu);
    }

    public function testSearchConfigAllProperties(): void
    {
        $config = new TopbarSearchConfig(
            action: '/imp/search',
            label: 'Search',
            iconUrl: '/search.png',
            parameters: ['page' => 'mailbox', 'type' => 'message'],
            hasMenu: true,
        );

        $this->assertSame(['page' => 'mailbox', 'type' => 'message'], $config->parameters);
        $this->assertTrue($config->hasMenu);
    }

    public function testTopbarDataRequiredOnly(): void
    {
        $data = new TopbarData(portalUrl: '/horde/', version: 'H6');

        $this->assertSame('/horde/', $data->portalUrl);
        $this->assertSame('H6', $data->version);
        $this->assertSame([], $data->menuTree);
        $this->assertNull($data->searchConfig);
        $this->assertNull($data->logoutUrl);
        $this->assertNull($data->loginUrl);
        $this->assertSame('', $data->date);
        $this->assertTrue($data->sidebarEnabled);
        $this->assertSame(150, $data->sidebarWidth);
        $this->assertSame([], $data->jsConfig);
        $this->assertNull($data->subinfo);
    }

    public function testTopbarDataAllProperties(): void
    {
        $search = new TopbarSearchConfig(
            action: '/search',
            label: 'Search',
            iconUrl: '/icon.png',
        );
        $node = new TopbarMenuNode(id: 'horde', label: 'Horde');

        $data = new TopbarData(
            portalUrl: '/horde/portal',
            version: 'Horde 6.0.0',
            menuTree: [$node],
            searchConfig: $search,
            logoutUrl: '/horde/login/logout',
            loginUrl: '/horde/login',
            date: 'April 21, 2026',
            sidebarEnabled: false,
            sidebarWidth: 200,
            jsConfig: ['app' => 'horde', 'token' => 'abc123'],
            subinfo: 'admin@example.com',
        );

        $this->assertCount(1, $data->menuTree);
        $this->assertSame($search, $data->searchConfig);
        $this->assertSame('/horde/login/logout', $data->logoutUrl);
        $this->assertSame('/horde/login', $data->loginUrl);
        $this->assertSame('April 21, 2026', $data->date);
        $this->assertFalse($data->sidebarEnabled);
        $this->assertSame(200, $data->sidebarWidth);
        $this->assertSame('abc123', $data->jsConfig['token']);
        $this->assertSame('admin@example.com', $data->subinfo);
    }
}
