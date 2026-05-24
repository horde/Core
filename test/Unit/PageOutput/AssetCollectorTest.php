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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssetCollector::class)]
class AssetCollectorTest extends TestCase
{
    private AssetCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new AssetCollector();
    }

    public function testAddScriptStoresUrl(): void
    {
        $this->collector->addScript('/js/app.js');
        $this->assertSame(['/js/app.js'], $this->collector->getScriptUrls());
    }

    public function testAddScriptDeduplicates(): void
    {
        $this->collector->addScript('/js/app.js');
        $this->collector->addScript('/js/app.js');
        $this->assertCount(1, $this->collector->getScriptUrls());
    }

    public function testAddScriptPreservesOrder(): void
    {
        $this->collector->addScript('/js/a.js');
        $this->collector->addScript('/js/b.js');
        $this->collector->addScript('/js/c.js');
        $this->assertSame(['/js/a.js', '/js/b.js', '/js/c.js'], $this->collector->getScriptUrls());
    }

    public function testAddStylesheetStoresUrl(): void
    {
        $this->collector->addStylesheet('/css/style.css');
        $this->assertSame(['/css/style.css'], $this->collector->getStylesheetUrls());
    }

    public function testAddStylesheetDeduplicates(): void
    {
        $this->collector->addStylesheet('/css/style.css');
        $this->collector->addStylesheet('/css/style.css');
        $this->assertCount(1, $this->collector->getStylesheetUrls());
    }

    public function testRenderScriptTags(): void
    {
        $this->collector->addScript('/js/proto.js');
        $this->collector->addScript('/js/app.js');

        $html = $this->collector->renderScriptTags();

        $this->assertStringContainsString('src="/js/proto.js"', $html);
        $this->assertStringContainsString('src="/js/app.js"', $html);
        $this->assertStringContainsString('<script type="text/javascript"', $html);
    }

    public function testRenderScriptTagsEmpty(): void
    {
        $this->assertSame('', $this->collector->renderScriptTags());
    }

    public function testRenderScriptTagsEscapesUrl(): void
    {
        $this->collector->addScript('/js/app.js?v=1&t=2');
        $html = $this->collector->renderScriptTags();
        $this->assertStringContainsString('src="/js/app.js?v=1&amp;t=2"', $html);
    }

    public function testRenderStylesheetTags(): void
    {
        $this->collector->addStylesheet('/css/base.css');
        $html = $this->collector->renderStylesheetTags();

        $this->assertStringContainsString('rel="stylesheet"', $html);
        $this->assertStringContainsString('href="/css/base.css"', $html);
    }

    public function testRenderStylesheetTagsEmpty(): void
    {
        $this->assertSame('', $this->collector->renderStylesheetTags());
    }

    public function testAddMetaTagAndRender(): void
    {
        $this->collector->addMetaTag('content-type', 'text/html; charset=UTF-8');
        $html = $this->collector->renderMetaTags();

        $this->assertStringContainsString('http-equiv="content-type"', $html);
        $this->assertStringContainsString('content="text/html; charset=UTF-8"', $html);
    }

    public function testAddMetaTagNameAttribute(): void
    {
        $this->collector->addMetaTag('viewport', 'width=device-width', false);
        $html = $this->collector->renderMetaTags();

        $this->assertStringContainsString('name="viewport"', $html);
        $this->assertStringNotContainsString('http-equiv', $html);
    }

    public function testMetaTagOverwritesByName(): void
    {
        $this->collector->addMetaTag('refresh', '5;url=/old');
        $this->collector->addMetaTag('refresh', '10;url=/new');
        $html = $this->collector->renderMetaTags();

        $this->assertStringContainsString('10;url=/new', $html);
        $this->assertStringNotContainsString('/old', $html);
    }

    public function testRenderMetaTagsEmpty(): void
    {
        $this->assertSame('', $this->collector->renderMetaTags());
    }

    public function testAddLinkTagDefaults(): void
    {
        $this->collector->addLinkTag(['href' => '/feed.rss', 'title' => 'RSS Feed']);
        $html = $this->collector->renderLinkTags();

        $this->assertStringContainsString('rel="alternate"', $html);
        $this->assertStringContainsString('type="application/rss+xml"', $html);
        $this->assertStringContainsString('href="/feed.rss"', $html);
        $this->assertStringContainsString('title="RSS Feed"', $html);
    }

    public function testAddLinkTagEscapesAttributes(): void
    {
        $this->collector->addLinkTag(['href' => '/feed?a=1&b=2', 'title' => 'A "test"']);
        $html = $this->collector->renderLinkTags();

        $this->assertStringContainsString('href="/feed?a=1&amp;b=2"', $html);
        $this->assertStringContainsString('title="A &quot;test&quot;"', $html);
    }

    public function testRenderLinkTagsEmpty(): void
    {
        $this->assertSame('', $this->collector->renderLinkTags());
    }

    public function testAddJsVarAndRender(): void
    {
        $this->collector->addJsVar('HordeCore.conf', ['token' => 'abc']);
        $html = $this->collector->renderJsVarBlock();

        $this->assertStringContainsString('HordeCore.conf={"token":"abc"};', $html);
        $this->assertStringContainsString('//<![CDATA[', $html);
        $this->assertStringContainsString('//]]>', $html);
    }

    public function testAddRawJsVarAndRender(): void
    {
        $this->collector->addRawJsVar('MyApp.init', 'new App()');
        $html = $this->collector->renderJsVarBlock();

        $this->assertStringContainsString('MyApp.init=new App();', $html);
    }

    public function testJsVarBlockTopOnlyFilter(): void
    {
        $this->collector->addJsVar('a', 1, true);
        $this->collector->addJsVar('b', 2, false);

        $topHtml = $this->collector->renderJsVarBlock(true);
        $allHtml = $this->collector->renderJsVarBlock(false);

        $this->assertStringContainsString('a=1;', $topHtml);
        $this->assertStringNotContainsString('b=2;', $topHtml);
        $this->assertStringContainsString('a=1;', $allHtml);
        $this->assertStringContainsString('b=2;', $allHtml);
    }

    public function testJsVarBlockEmpty(): void
    {
        $this->assertSame('', $this->collector->renderJsVarBlock());
    }

    public function testJsonEncodingUnescapedSlashes(): void
    {
        $this->collector->addJsVar('url', '/path/to/thing');
        $html = $this->collector->renderJsVarBlock();
        $this->assertStringContainsString('url="/path/to/thing";', $html);
    }

    public function testAddInlineScriptRaw(): void
    {
        $this->collector->addInlineScript('alert("hi")');
        $html = $this->collector->renderInlineScriptBlock(false);

        $this->assertStringContainsString('alert("hi");', $html);
        $this->assertStringContainsString('<script type="text/javascript">', $html);
    }

    public function testAddInlineScriptTrimsAndAppendsSemicolon(): void
    {
        $this->collector->addInlineScript('  x = 1;  ');
        $html = $this->collector->renderInlineScriptBlock(false);

        $this->assertStringContainsString('x = 1;', $html);
        $this->assertStringNotContainsString('x = 1;;', $html);
    }

    public function testAddInlineScriptIgnoresEmpty(): void
    {
        $this->collector->addInlineScript('');
        $this->collector->addInlineScript('   ');
        $this->assertSame('', $this->collector->renderInlineScriptBlock(false));
    }

    public function testInlineScriptPrototypeWrapping(): void
    {
        $this->collector->addInlineScript('init()', 'prototype');
        $html = $this->collector->renderInlineScriptBlock('prototype');

        $this->assertStringContainsString('document.observe("dom:loaded",function(){init();});', $html);
    }

    public function testInlineScriptJqueryWrapping(): void
    {
        $this->collector->addInlineScript('init()', 'jquery');
        $html = $this->collector->renderInlineScriptBlock('jquery');

        $this->assertStringContainsString('$(function(){init();});', $html);
    }

    public function testInlineScriptBlockTopFilter(): void
    {
        $this->collector->addInlineScript('a()', false, true);
        $this->collector->addInlineScript('b()', false, false);

        $topHtml = $this->collector->renderInlineScriptBlock(false, true);
        $allHtml = $this->collector->renderInlineScriptBlock(false, false);

        $this->assertStringContainsString('a();', $topHtml);
        $this->assertStringNotContainsString('b();', $topHtml);
        $this->assertStringContainsString('a();', $allHtml);
        $this->assertStringContainsString('b();', $allHtml);
    }

    public function testInlineScriptBlockEmptyOnload(): void
    {
        $this->assertSame('', $this->collector->renderInlineScriptBlock('prototype'));
    }

    public function testRenderAllInlineScriptsCombinesVarsAndBlocks(): void
    {
        $this->collector->addJsVar('x', 42);
        $this->collector->addInlineScript('doStuff()', false);
        $this->collector->addInlineScript('onReady()', 'prototype');

        $html = $this->collector->renderAllInlineScripts();

        $this->assertStringContainsString('x=42;', $html);
        $this->assertStringContainsString('doStuff();', $html);
        $this->assertStringContainsString('document.observe("dom:loaded",function(){onReady();});', $html);
    }

    public function testRenderAllInlineScriptsEmpty(): void
    {
        $this->assertSame('', $this->collector->renderAllInlineScripts());
    }

    public function testRenderAllInlineScriptsTopFilter(): void
    {
        $this->collector->addJsVar('top', 1, true);
        $this->collector->addJsVar('bottom', 2, false);
        $this->collector->addInlineScript('topFn()', false, true);
        $this->collector->addInlineScript('bottomFn()', false, false);

        $html = $this->collector->renderAllInlineScripts(true);

        $this->assertStringContainsString('top=1;', $html);
        $this->assertStringNotContainsString('bottom=2;', $html);
        $this->assertStringContainsString('topFn();', $html);
        $this->assertStringNotContainsString('bottomFn();', $html);
    }

    public function testCdataWrapping(): void
    {
        $this->collector->addInlineScript('test()');
        $html = $this->collector->renderInlineScriptBlock(false);

        $this->assertStringStartsWith('<script type="text/javascript">//<![CDATA[' . "\n", $html);
        $this->assertStringEndsWith("\n//]]></script>\n", $html);
    }

    public function testMultipleInlineScriptsSameOnload(): void
    {
        $this->collector->addInlineScript('a()');
        $this->collector->addInlineScript('b()');
        $html = $this->collector->renderInlineScriptBlock(false);

        $this->assertStringContainsString('a();b();', $html);
    }

    public function testAddInlineStyleStores(): void
    {
        $this->collector->addInlineStyle('.form-field { color: red; }');
        $html = $this->collector->renderInlineStyleBlock();

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('.form-field { color: red; }', $html);
        $this->assertStringContainsString('</style>', $html);
    }

    public function testAddInlineStyleIgnoresEmpty(): void
    {
        $this->collector->addInlineStyle('');
        $this->collector->addInlineStyle('   ');
        $this->assertSame('', $this->collector->renderInlineStyleBlock());
    }

    public function testRenderInlineStyleBlockEmptyWhenNoStyles(): void
    {
        $this->assertSame('', $this->collector->renderInlineStyleBlock());
    }

    public function testMultipleInlineStylesCombined(): void
    {
        $this->collector->addInlineStyle('.a { color: red; }');
        $this->collector->addInlineStyle('.b { color: blue; }');
        $html = $this->collector->renderInlineStyleBlock();

        $this->assertStringContainsString('.a { color: red; }', $html);
        $this->assertStringContainsString('.b { color: blue; }', $html);
    }
}
