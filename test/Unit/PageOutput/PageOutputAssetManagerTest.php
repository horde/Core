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
use Horde\Core\PageOutput\PageOutputAssetManager;
use Horde\Form\V3\Renderer\AssetManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageOutputAssetManager::class)]
class PageOutputAssetManagerTest extends TestCase
{
    private AssetCollector $collector;
    private PageOutputAssetManager $manager;

    protected function setUp(): void
    {
        $this->collector = new AssetCollector();
        $this->manager = new PageOutputAssetManager($this->collector);
    }

    public function testImplementsAssetManagerInterface(): void
    {
        $this->assertInstanceOf(AssetManager::class, $this->manager);
    }

    public function testAddScriptDelegatesToCollector(): void
    {
        $this->manager->addScript('/js/datepicker.js');
        $this->assertSame(['/js/datepicker.js'], $this->collector->getScriptUrls());
    }

    public function testAddStylesheetDelegatesToCollector(): void
    {
        $this->manager->addStylesheet('/css/datepicker.css');
        $this->assertSame(['/css/datepicker.css'], $this->collector->getStylesheetUrls());
    }

    public function testAddInlineScriptDelegatesToCollector(): void
    {
        $this->manager->addInlineScript('initForm()');
        $html = $this->collector->renderAllInlineScripts();
        $this->assertStringContainsString('initForm();', $html);
    }

    public function testAddInlineStyleDelegatesToCollector(): void
    {
        $this->manager->addInlineStyle('.field { display: block; }');
        $html = $this->collector->renderInlineStyleBlock();
        $this->assertStringContainsString('.field { display: block; }', $html);
    }

    public function testRenderReturnsEmptyString(): void
    {
        $this->manager->addScript('/js/app.js');
        $this->manager->addInlineScript('init()');
        $this->assertSame('', $this->manager->render());
    }

    public function testClearDoesNotAffectCollector(): void
    {
        $this->manager->addScript('/js/app.js');
        $this->manager->clear();
        $this->assertSame(['/js/app.js'], $this->collector->getScriptUrls());
    }

    public function testScriptDeduplicationViaCollector(): void
    {
        $this->manager->addScript('/js/datepicker.js');
        $this->manager->addScript('/js/datepicker.js');
        $this->assertCount(1, $this->collector->getScriptUrls());
    }

    public function testAssetsAppearInPageComposerOutput(): void
    {
        $this->manager->addStylesheet('/css/form.css');
        $this->manager->addScript('/js/form.js');
        $this->manager->addInlineScript('formInit()');
        $this->manager->addInlineStyle('.required { border: 1px solid red; }');

        $stylesheetHtml = $this->collector->renderStylesheetTags();
        $this->assertStringContainsString('/css/form.css', $stylesheetHtml);

        $scriptHtml = $this->collector->renderScriptTags();
        $this->assertStringContainsString('/js/form.js', $scriptHtml);

        $inlineJs = $this->collector->renderAllInlineScripts();
        $this->assertStringContainsString('formInit();', $inlineJs);

        $inlineCss = $this->collector->renderInlineStyleBlock();
        $this->assertStringContainsString('.required { border: 1px solid red; }', $inlineCss);
    }
}
