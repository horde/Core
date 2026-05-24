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

use Closure;
use Horde\Core\Assets\CssDiscoverer;
use Horde\Core\Assets\CssDiscoveryRequest;
use Horde\Core\Assets\JsDiscoverer;
use Horde\Injector\Attribute\Factory;
use Psr\Http\Message\ServerRequestInterface;

#[Factory(factory: ResponsiveChromeRendererFactory::class, method: 'create')]
class ResponsiveChromeRenderer implements ChromeRenderer
{
    /** @var Closure(string): string */
    private readonly Closure $topbarFactory;

    /**
     * @param Closure(string): string $topbarFactory Callable that takes app name, returns topbar HTML
     */
    public function __construct(
        private readonly CssDiscoverer $cssDiscoverer,
        private readonly JsDiscoverer $jsDiscoverer,
        Closure $topbarFactory,
    ) {
        $this->topbarFactory = $topbarFactory;
    }

    public function renderPage(PageContent $content, ServerRequestInterface $request): string
    {
        $app = $content->app;

        $cssUrls = $this->buildCssUrls($app);
        $jsUrls = $this->buildJsUrls($app, $content->jsFiles);
        $topbarHtml = $this->buildTopbar($app);

        return $this->assemblePage($content->title, $cssUrls, $topbarHtml, $content->bodyHtml, $jsUrls);
    }

    /** @return string[] */
    private function buildCssUrls(string $app): array
    {
        $request = new CssDiscoveryRequest(
            files: ['screen.css'],
            app: $app,
        );
        $result = $this->cssDiscoverer->discover($request);

        $urls = [];
        foreach ($result as $entry) {
            $urls[] = $entry->uri;
        }

        return $urls;
    }

    /**
     * @param string[] $extraFiles
     * @return string[]
     */
    private function buildJsUrls(string $app, array $extraFiles): array
    {
        $jsUrls = [];

        $coreFiles = ['responsive-topbar.js'];
        foreach ($coreFiles as $file) {
            $uri = $this->jsDiscoverer->resolve($file, 'horde');
            if ($uri !== null) {
                $jsUrls[] = $uri;
            }
        }

        foreach ($extraFiles as $file) {
            $uri = $this->jsDiscoverer->resolve($file, $app !== 'horde' ? $app : 'horde');
            if ($uri !== null) {
                $jsUrls[] = $uri;
            }
        }

        $uri = $this->jsDiscoverer->resolve('responsive.js', $app);
        if ($uri !== null) {
            $jsUrls[] = $uri;
        }

        return $jsUrls;
    }

    private function buildTopbar(string $app): string
    {
        return ($this->topbarFactory)($app);
    }

    /**
     * @param string[] $cssUrls
     * @param string[] $jsUrls
     */
    private function assemblePage(
        string $title,
        array $cssUrls,
        string $topbarHtml,
        string $bodyHtml,
        array $jsUrls,
    ): string {
        $html = '<!DOCTYPE html>' . "\n";
        $html .= '<html lang="en">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '  <meta charset="UTF-8">' . "\n";
        $html .= '  <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        $html .= '  <title>' . $this->esc($title) . '</title>' . "\n";

        foreach ($cssUrls as $url) {
            $html .= '  <link rel="stylesheet" href="' . $this->esc($url) . '">' . "\n";
        }

        $html .= '</head>' . "\n";
        $html .= '<body class="horde-responsive">' . "\n";
        $html .= $topbarHtml . "\n";
        $html .= $bodyHtml . "\n";

        foreach ($jsUrls as $url) {
            $html .= '<script src="' . $this->esc($url) . '"></script>' . "\n";
        }

        $html .= '</body>' . "\n";
        $html .= '</html>' . "\n";

        return $html;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
