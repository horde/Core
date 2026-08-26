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

/**
 * Theme-aware JavaScript discoverer.
 *
 * The flat resolve()/resolveMany() API keeps its existing app-JS-dir
 * behaviour. discoverTheme() adds a theme cascade parallel to the CSS
 * discoverer: a theme may ship its own scripts (declared in info.php) and
 * an application-level theme layers on top of the base 'horde' theme, so an
 * app can add to or override the scripts a shared theme ships.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ThemeJsDiscoverer implements JsDiscoverer
{
    public function __construct(
        private readonly PathBuilderInterface $pathBuilder,
        private readonly UriBuilderInterface $uriBuilder,
        private readonly AssetFilesystem $filesystem,
        private readonly ThemeInfoReader $themeInfo,
    ) {}

    public function resolve(string $file, string $app = 'horde'): ?string
    {
        $fsPath = (string) $this->pathBuilder
            ->withAppJsDir($app)
            ->withPart($file);

        if (!$this->filesystem->fileExists($fsPath)) {
            return null;
        }

        return (string) $this->uriBuilder
            ->withJsUri($app)
            ->withPart($file);
    }

    public function resolveMany(array $files, string $app = 'horde'): array
    {
        $result = [];
        foreach ($files as $file) {
            $result[$file] = $this->resolve($file, $app);
        }

        return $result;
    }

    public function discoverTheme(JsDiscoveryRequest $request): JsDiscoveryResult
    {
        $entries = [];

        /* Base 'horde' theme first, then the application theme on top. */
        $this->collectLevel($entries, 'horde', $request->theme, $request->files);
        if ($request->app !== 'horde') {
            $this->collectLevel($entries, $request->app, $request->theme, $request->files);
        }

        return new JsDiscoveryResult($entries, $request->theme, $request->app);
    }

    /**
     * @param list<JsAssetEntry> $entries
     * @param list<string>       $explicitFiles  Caller-supplied files; when
     *                                           empty the theme's own
     *                                           declarations are used.
     */
    private function collectLevel(array &$entries, string $app, string $theme, array $explicitFiles): void
    {
        $files = $explicitFiles !== []
            ? $explicitFiles
            : $this->themeInfo->readScripts($app, $theme);

        foreach ($files as $file) {
            $fsPath = (string) $this->pathBuilder
                ->withAppThemesDir($app)
                ->withSlug($theme)
                ->withPart($file);

            if (!$this->filesystem->fileExists($fsPath)) {
                continue;
            }

            $uri = (string) $this->uriBuilder
                ->withThemesUri($app)
                ->withSlug($theme)
                ->withPart($file);

            $entries[] = new JsAssetEntry($fsPath, $uri, $app);
        }
    }
}
