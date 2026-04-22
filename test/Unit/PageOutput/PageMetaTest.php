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

use Horde\Core\PageOutput\PageMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageMeta::class)]
class PageMetaTest extends TestCase
{
    public function testRequiredTitleOnly(): void
    {
        $meta = new PageMeta(title: 'My Page');

        $this->assertSame('My Page', $meta->title);
        $this->assertNull($meta->language);
        $this->assertNull($meta->bodyClass);
        $this->assertNull($meta->bodyId);
        $this->assertNull($meta->htmlId);
        $this->assertNull($meta->faviconUrl);
        $this->assertTrue($meta->deferScripts);
    }

    public function testAllProperties(): void
    {
        $meta = new PageMeta(
            title: 'Inbox - Mail',
            language: 'en_US',
            bodyClass: 'horde-dynamic',
            bodyId: 'imp-page',
            htmlId: 'htmlImp',
            faviconUrl: '/themes/default/graphics/favicon.ico',
            deferScripts: false,
        );

        $this->assertSame('Inbox - Mail', $meta->title);
        $this->assertSame('en_US', $meta->language);
        $this->assertSame('horde-dynamic', $meta->bodyClass);
        $this->assertSame('imp-page', $meta->bodyId);
        $this->assertSame('htmlImp', $meta->htmlId);
        $this->assertSame('/themes/default/graphics/favicon.ico', $meta->faviconUrl);
        $this->assertFalse($meta->deferScripts);
    }

    public function testDeferScriptsDefaultsToTrue(): void
    {
        $meta = new PageMeta(title: 'Test');
        $this->assertTrue($meta->deferScripts);
    }
}
