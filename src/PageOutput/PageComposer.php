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

/**
 * Assembles the HTML head and foot for traditional desktop pages.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: PageComposerFactory::class, method: 'create')]
class PageComposer
{
    public function __construct(
        private readonly AssetCollector $assetCollector,
    ) {}

    public function renderHead(PageMeta $meta): string
    {
        $html = '<!DOCTYPE html>' . "\n";

        $htmlAttrs = '';
        if ($meta->language !== null) {
            $htmlAttrs .= ' lang="' . htmlspecialchars($meta->language, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        if ($meta->htmlId !== null) {
            $htmlAttrs .= ' id="' . htmlspecialchars($meta->htmlId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        $html .= '<html' . $htmlAttrs . '>' . "\n";

        $html .= " <head>\n";
        $html .= '  ' . $this->assetCollector->renderMetaTags();
        $html .= '  ' . $this->assetCollector->renderStylesheetTags();

        if ($meta->faviconUrl !== null) {
            $html .= '  <link type="image/x-icon" href="'
                . htmlspecialchars($meta->faviconUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . "\" />\n";
        }

        $html .= '  ' . $this->assetCollector->renderLinkTags();

        if (!$meta->deferScripts) {
            $html .= '  ' . $this->assetCollector->renderScriptTags();
            $html .= '  ' . $this->assetCollector->renderAllInlineScripts();
        }

        $html .= '  <title>' . htmlspecialchars($meta->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</title>\n";
        $html .= " </head>\n\n";

        $bodyAttrs = '';
        if ($meta->bodyClass !== null) {
            $bodyAttrs .= ' class="' . htmlspecialchars($meta->bodyClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        if ($meta->bodyId !== null) {
            $bodyAttrs .= ' id="' . htmlspecialchars($meta->bodyId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        $html .= ' <body' . $bodyAttrs . '>' . "\n";

        return $html;
    }

    public function renderFoot(): string
    {
        $html = '  ' . $this->assetCollector->renderScriptTags();
        $html .= '  ' . $this->assetCollector->renderAllInlineScripts();
        $html .= " </body>\n</html>\n";

        return $html;
    }
}
