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

namespace Horde\Core\Assets;

use Horde\Core\Path\PathBuilderInterface;
use Horde\Core\Uri\UriBuilderInterface;

class CascadeCssDiscoverer implements CssDiscoverer
{
    public function __construct(
        private readonly PathBuilderInterface $pathBuilder,
        private readonly UriBuilderInterface $uriBuilder,
        private readonly AssetFilesystem $filesystem,
        private readonly ?TextDirectionProvider $textDirection = null,
        private readonly ?CssHookProvider $hookProvider = null,
    ) {}

    public function discover(CssDiscoveryRequest $request): CssDiscoveryResult
    {
        $entries = [];

        foreach ($request->files as $file) {
            $this->walkCascade($entries, $file, $request->app, $request->theme, $request->subView);
        }

        if ($this->textDirection !== null && $this->textDirection->isRtl()) {
            $this->walkCascade($entries, 'rtl.css', $request->app, $request->theme, $request->subView);
        }

        if ($this->hookProvider !== null) {
            foreach ($this->hookProvider->getHookFiles($request->app, $request->theme) as $fsPath => $uri) {
                $entries[] = new CssAssetEntry($fsPath, $uri, $request->app);
            }
        }

        return new CssDiscoveryResult($entries, $request->theme, $request->app);
    }

    /** @param list<CssAssetEntry> $entries */
    private function walkCascade(array &$entries, string $file, string $app, string $theme, ?string $subView): void
    {
        $this->checkLevel($entries, $file, 'horde', 'default');
        if ($subView !== null) {
            $this->checkLevel($entries, $file, 'horde', 'default', $subView);
        }

        if ($theme !== 'default') {
            $this->checkLevel($entries, $file, 'horde', $theme);
            if ($subView !== null) {
                $this->checkLevel($entries, $file, 'horde', $theme, $subView);
            }
        }

        if ($app !== 'horde') {
            $this->checkLevel($entries, $file, $app, 'default');
            if ($subView !== null) {
                $this->checkLevel($entries, $file, $app, 'default', $subView);
            }

            if ($theme !== 'default') {
                $this->checkLevel($entries, $file, $app, $theme);
                if ($subView !== null) {
                    $this->checkLevel($entries, $file, $app, $theme, $subView);
                }
            }
        }
    }

    /** @param list<CssAssetEntry> $entries */
    private function checkLevel(
        array &$entries,
        string $file,
        string $app,
        string $themeName,
        ?string $subView = null,
    ): void {
        $pathPart = $subView !== null ? $subView . '/' . $file : $file;

        $fsPath = (string) $this->pathBuilder
            ->withAppThemesDir($app)
            ->withSlug($themeName)
            ->withPart($pathPart);

        if ($this->filesystem->fileExists($fsPath)) {
            $uri = (string) $this->uriBuilder
                ->withThemesUri($app)
                ->withSlug($themeName)
                ->withPart($pathPart);

            $entries[] = new CssAssetEntry($fsPath, $uri, $app);
        }
    }
}
