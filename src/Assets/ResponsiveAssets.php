<?php

declare(strict_types=1);

namespace Horde\Core\Assets;

use Horde\Core\Config\RegistryState;
use Exception;

/**
 * Responsive Assets Helper
 *
 * Provides CSS and JavaScript URLs for responsive templates with
 * automatic cascade support (horde base + app overrides).
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class ResponsiveAssets
{
    public function __construct(
        private readonly RegistryState $registryState,
        private readonly ResponsiveAssetsFilesystem $filesystem = new ResponsiveAssetsFilesystemImpl(),
    ) {}

    /**
     * Look up a registry parameter for an app, falling back to horde.
     */
    private function getParam(string $parameter, string $app): ?string
    {
        $appConfig = $this->registryState->getApplication($app);
        if ($appConfig !== null && isset($appConfig[$parameter])) {
            return $appConfig[$parameter];
        }
        $hordeConfig = $this->registryState->getApplication('horde');
        return $hordeConfig[$parameter] ?? null;
    }

    /**
     * Get list of responsive CSS URLs to load
     *
     * Returns URLs in cascade order:
     * 1. Horde default theme CSS (always)
     * 2. Horde selected theme CSS (if different from default and exists)
     * 3. Application default theme CSS (if exists and app != horde)
     * 4. Application selected theme CSS (if different from default and exists)
     *
     * @param string $app Application name
     * @param string|null $theme Theme name (null = use preference)
     * @return array<string> Array of CSS URLs
     */
    public function getCssUrls(string $app, ?string $theme = null): array
    {
        $urls = [];
        $theme ??= $this->getThemePreference();

        $cssFiles = ['screen.css'];

        foreach ($cssFiles as $file) {
            if ($this->cssFileExists($file, 'default', 'horde')) {
                $urls[] = $this->buildCssUrl($file, 'default', 'horde');
            }

            if ($theme !== 'default' && $this->cssFileExists($file, $theme, 'horde')) {
                $urls[] = $this->buildCssUrl($file, $theme, 'horde');
            }

            if ($app !== 'horde' && $this->cssFileExists($file, 'default', $app)) {
                $urls[] = $this->buildCssUrl($file, 'default', $app);
            }

            if ($app !== 'horde' && $theme !== 'default' && $this->cssFileExists($file, $theme, $app)) {
                $urls[] = $this->buildCssUrl($file, $theme, $app);
            }
        }

        return $urls;
    }

    /**
     * Get list of responsive JavaScript URLs to load
     *
     * @param string $app Application name
     * @param array<string> $jsFiles List of JS filenames to load
     * @return array<string> Array of JS URLs
     */
    public function getJsUrls(string $app, array $jsFiles = []): array
    {
        $urls = [];

        foreach ($jsFiles as $file) {
            if ($this->jsFileExists($file, 'horde')) {
                $urls[] = $this->buildJsUrl($file, 'horde');
            }

            if ($app !== 'horde' && $this->jsFileExists($file, $app)) {
                $urls[] = $this->buildJsUrl($file, $app);
            }
        }

        return $urls;
    }

    /**
     * Get current theme name
     *
     * @return string Theme name
     */
    public function getTheme(): string
    {
        return $this->getThemePreference();
    }

    /**
     * Get URL for a theme graphic (first-found-wins cascade)
     *
     * @param string $file Graphic filename
     * @param string $app Application name
     * @param string|null $theme Theme name (null = use preference)
     * @return string URL to the graphic, or '' if not found
     */
    public function getGraphicUrl(
        string $file,
        string $app,
        ?string $theme = null,
    ): string {
        $theme ??= $this->getThemePreference();

        $candidates = [];

        if ($app !== 'horde' && $theme !== 'default') {
            $candidates[] = ['theme' => $theme, 'app' => $app];
        }

        if ($app !== 'horde') {
            $candidates[] = ['theme' => 'default', 'app' => $app];
        }

        if ($theme !== 'default') {
            $candidates[] = ['theme' => $theme, 'app' => 'horde'];
        }

        $candidates[] = ['theme' => 'default', 'app' => 'horde'];

        foreach ($candidates as $candidate) {
            if ($this->graphicFileExists($file, $candidate['theme'], $candidate['app'])) {
                return $this->buildGraphicUrl($file, $candidate['theme'], $candidate['app']);
            }
        }

        return '';
    }

    /**
     * Build CSS URL
     *
     * @param string $file CSS filename
     * @param string $theme Theme name
     * @param string $app Application name
     * @return string URL
     */
    private function buildCssUrl(string $file, string $theme, string $app): string
    {
        return $this->getParam('themesuri', $app) . '/' . $theme . '/' . $file;
    }

    /**
     * Build JS URL
     *
     * @param string $file JS filename
     * @param string $app Application name
     * @return string URL
     */
    private function buildJsUrl(string $file, string $app): string
    {
        return $this->getParam('jsuri', $app) . '/' . $file;
    }

    /**
     * Check if CSS file exists
     *
     * @param string $filename CSS filename
     * @param string $theme Theme name
     * @param string $app Application name
     * @return bool
     */
    private function cssFileExists(string $filename, string $theme, string $app): bool
    {
        try {
            $themesFs = $this->getParam('themesfs', $app);
            if ($themesFs === null) {
                return false;
            }
            return $this->filesystem->fileExists($themesFs . '/' . $theme . '/' . $filename);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if JS file exists
     *
     * @param string $filename JS filename
     * @param string $app Application name
     * @return bool
     */
    private function jsFileExists(string $filename, string $app): bool
    {
        try {
            $jsFs = $this->getParam('jsfs', $app);
            if ($jsFs === null) {
                return false;
            }
            return $this->filesystem->fileExists($jsFs . '/' . $filename);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Build graphic URL
     *
     * @param string $file Graphic filename
     * @param string $theme Theme name
     * @param string $app Application name
     * @return string URL
     */
    private function buildGraphicUrl(string $file, string $theme, string $app): string
    {
        return $this->getParam('themesuri', $app) . '/' . $theme . '/graphics/' . $file;
    }

    /**
     * Check if graphic file exists
     *
     * @param string $filename Graphic filename
     * @param string $theme Theme name
     * @param string $app Application name
     * @return bool
     */
    private function graphicFileExists(string $filename, string $theme, string $app): bool
    {
        try {
            $themesFs = $this->getParam('themesfs', $app);
            if ($themesFs === null) {
                return false;
            }
            return $this->filesystem->fileExists($themesFs . '/' . $theme . '/graphics/' . $filename);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get theme preference
     *
     * @return string Theme name
     */
    private function getThemePreference(): string
    {
        $prefs = $GLOBALS['prefs'] ?? null;
        return $prefs ? ($prefs->getValue('theme') ?? 'default') : 'default';
    }
}
