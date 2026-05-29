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

namespace Horde\Core\PageOutput;

use Horde\Form\V3\Renderer\AssetManager;

/**
 * Adapter bridging Form V3's AssetManager to Core's page-level AssetCollector.
 *
 * When injected into HtmlRenderer, form-required assets (scripts,
 * stylesheets, inline code) are collected by the page's AssetCollector
 * instead of being rendered inline after the form. PageComposer then
 * places them in the correct document location (head or deferred foot).
 *
 * Usage:
 *
 *     $assetManager = new PageOutputAssetManager($assetCollector);
 *     $renderer = new HtmlRenderer(assetManager: $assetManager);
 *     $formHtml = $renderer->render($form, $url, 'post');
 *     // $formHtml contains no <script>/<link> tags
 *     // Assets appear when PageComposer renders head/foot
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: PageOutputAssetManagerFactory::class, method: 'create')]
class PageOutputAssetManager implements AssetManager
{
    public function __construct(
        private readonly AssetCollector $assetCollector,
    ) {}

    public function addScript(string $file, array $attrs = []): void
    {
        $this->assetCollector->addScript($file);
    }

    public function addStylesheet(string $file, array $attrs = []): void
    {
        $this->assetCollector->addStylesheet($file);
    }

    public function addInlineScript(string $code): void
    {
        $this->assetCollector->addInlineScript($code);
    }

    public function addInlineStyle(string $code): void
    {
        $this->assetCollector->addInlineStyle($code);
    }

    public function render(): string
    {
        return '';
    }

    public function clear(): void {}
}
