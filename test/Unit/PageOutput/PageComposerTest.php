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
use Horde\Core\PageOutput\PageComposer;
use Horde\Core\PageOutput\PageMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageComposer::class)]
class PageComposerTest extends TestCase
{
    private AssetCollector $collector;
    private PageComposer $composer;

    protected function setUp(): void
    {
        $this->collector = new AssetCollector();
        $this->composer = new PageComposer($this->collector);
    }

    public function testRenderHeadMinimal(): void
    {
        $meta = new PageMeta(title: 'Test Page');
        $html = $this->composer->renderHead($meta);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html>', $html);
        $this->assertStringContainsString('<head>', $html);
        $this->assertStringContainsString('<title>Test Page</title>', $html);
        $this->assertStringContainsString('</head>', $html);
        $this->assertStringContainsString('<body>', $html);
    }

    public function testRenderHeadWithLanguage(): void
    {
        $meta = new PageMeta(title: 'Test', language: 'en_US');
        $html = $this->composer->renderHead($meta);

        $this->assertStringContainsString('lang="en_US"', $html);
    }

    public function testRenderHeadWithHtmlId(): void
    {
        $meta = new PageMeta(title: 'Test', htmlId: 'htmlImp');
        $html = $this->composer->renderHead($meta);

        $this->assertStringContainsString('id="htmlImp"', $html);
    }

    public function testRenderHeadWithBodyAttributes(): void
    {
        $meta = new PageMeta(title: 'Test', bodyClass: 'horde-dynamic', bodyId: 'imp-page');
        $html = $this->composer->renderHead($meta);

        $this->assertStringContainsString('class="horde-dynamic"', $html);
        $this->assertStringContainsString('id="imp-page"', $html);
    }

    public function testRenderHeadWithFavicon(): void
    {
        $meta = new PageMeta(title: 'Test', faviconUrl: '/favicon.ico');
        $html = $this->composer->renderHead($meta);

        $this->assertStringContainsString('href="/favicon.ico"', $html);
        $this->assertStringContainsString('image/x-icon', $html);
    }

    public function testRenderHeadEscapesTitle(): void
    {
        $meta = new PageMeta(title: 'A <b>bold</b> & "title"');
        $html = $this->composer->renderHead($meta);

        $this->assertStringContainsString('<title>A &lt;b&gt;bold&lt;/b&gt; &amp; &quot;title&quot;</title>', $html);
    }

    public function testRenderHeadIncludesMetaTags(): void
    {
        $this->collector->addMetaTag('content-type', 'text/html; charset=UTF-8');
        $meta = new PageMeta(title: 'Test');
        $html = $this->composer->renderHead($meta);

        $this->assertStringContainsString('http-equiv="content-type"', $html);
    }

    public function testRenderHeadIncludesStylesheets(): void
    {
        $this->collector->addStylesheet('/css/app.css');
        $meta = new PageMeta(title: 'Test');
        $html = $this->composer->renderHead($meta);

        $this->assertStringContainsString('href="/css/app.css"', $html);
    }

    public function testRenderHeadNonDeferredScripts(): void
    {
        $this->collector->addScript('/js/app.js');
        $meta = new PageMeta(title: 'Test', deferScripts: false);
        $html = $this->composer->renderHead($meta);

        $this->assertStringContainsString('src="/js/app.js"', $html);
    }

    public function testRenderHeadDeferredScriptsNotInHead(): void
    {
        $this->collector->addScript('/js/app.js');
        $meta = new PageMeta(title: 'Test', deferScripts: true);
        $html = $this->composer->renderHead($meta);

        $this->assertStringNotContainsString('src="/js/app.js"', $html);
    }

    public function testRenderFootIncludesScripts(): void
    {
        $this->collector->addScript('/js/app.js');
        $this->collector->addInlineScript('init()');
        $html = $this->composer->renderFoot();

        $this->assertStringContainsString('src="/js/app.js"', $html);
        $this->assertStringContainsString('init();', $html);
        $this->assertStringContainsString('</body>', $html);
        $this->assertStringContainsString('</html>', $html);
    }
}
