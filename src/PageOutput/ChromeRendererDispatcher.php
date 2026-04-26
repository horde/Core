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

use Horde\Injector\Attribute\Factory;
use Psr\Http\Message\ServerRequestInterface;

#[Factory(factory: ChromeRendererDispatcherFactory::class, method: 'create')]
class ChromeRendererDispatcher implements ChromeRenderer
{
    public function __construct(
        private readonly DesktopChromeRenderer $desktop,
        private readonly ResponsiveChromeRenderer $responsive,
    ) {}

    public function renderPage(PageContent $content, ServerRequestInterface $request): string
    {
        $mode = $request->getAttribute('renderingMode', RenderingMode::DYNAMIC);

        if ($mode === RenderingMode::RESPONSIVE) {
            return $this->responsive->renderPage($content, $request);
        }

        return $this->desktop->renderPage($content, $request);
    }
}
