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

/**
 * Reads theme asset declarations from a theme's info.php on the local
 * filesystem.
 *
 * Applies the same validation as the legacy Horde_Themes_Cache::themeScripts():
 * theme names and script file names are constrained so a declaration can never
 * escape the theme directory.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class PhpThemeInfoReader implements ThemeInfoReader
{
    /**
     * Plain directory name: no separators, no '..'. Theme names originate from
     * user prefs/options, so this guards the info.php include against path
     * traversal.
     */
    private const THEME_NAME = '/^[A-Za-z0-9_-]+$/';

    /**
     * Plain *.js file name. Combined with the explicit '..' check below this
     * keeps a declared script inside the theme directory.
     */
    private const SCRIPT_NAME = '/^[A-Za-z0-9_.-]+\.js$/';

    public function __construct(
        private readonly PathBuilderInterface $pathBuilder,
        private readonly AssetFilesystem $filesystem,
    ) {}

    public function readScripts(string $app, string $theme): array
    {
        if (!preg_match(self::THEME_NAME, $theme)) {
            return [];
        }

        $scripts = [];
        foreach ($this->declaredScripts($app, $theme) as $script) {
            $script = (string) $script;
            if (!preg_match(self::SCRIPT_NAME, $script) || strpos($script, '..') !== false) {
                continue;
            }

            $fsPath = (string) $this->pathBuilder
                ->withAppThemesDir($app)
                ->withSlug($theme)
                ->withPart($script);

            if ($this->filesystem->isReadable($fsPath)) {
                $scripts[] = $script;
            }
        }

        return $scripts;
    }

    /**
     * Include the theme's info.php in an isolated scope and return its
     * declared $theme_scripts. Any read/parse failure yields an empty array.
     *
     * @return array<int|string, mixed>
     */
    private function declaredScripts(string $app, string $theme): array
    {
        $info = (string) $this->pathBuilder
            ->withAppThemesDir($app)
            ->withSlug($theme)
            ->withPart('info.php');

        if (!$this->filesystem->isReadable($info)) {
            return [];
        }

        /* Declared before the include so the theme file only ever augments a
         * known-shape local; nothing from the outer scope leaks in. */
        $theme_scripts = [];

        include $info;

        return (array) $theme_scripts;
    }
}
