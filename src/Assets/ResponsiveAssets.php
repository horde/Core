<?php

declare(strict_types=1);

namespace Horde\Core\Assets;

use Horde_Registry;
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
    /**
     * Registry instance
     */
    private Horde_Registry $registry;

    /**
     * Filesystem abstraction for testability
     */
    private ResponsiveAssetsFilesystem $filesystem;

    /**
     * Constructor
     *
     * @param Horde_Registry $registry Horde registry instance
     * @param ResponsiveAssetsFilesystem|null $filesystem Filesystem implementation (for testing)
     */
    public function __construct(
        Horde_Registry $registry,
        ?ResponsiveAssetsFilesystem $filesystem = null
    ) {
        $this->registry = $registry;
        $this->filesystem = $filesystem ?? new ResponsiveAssetsFilesystemImpl();
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
     * @param string|null $theme Theme name (null = use preference)
     * @param string|null $app Application name (null = current app)
     * @return array<string> Array of CSS URLs
     */
    public function getCssUrls(?string $theme = null, ?string $app = null): array
    {
        $urls = [];
        $theme ??= $this->getThemePreference();
        $app ??= $this->registry->getApp();

        $cssFiles = ['responsive.css'];

        foreach ($cssFiles as $file) {
            // 1. Horde default theme (always load)
            if ($this->cssFileExists($file, 'default', 'horde')) {
                $urls[] = $this->buildCssUrl($file, 'default', 'horde');
            }

            // 2. Horde selected theme (cascade over default if different)
            if ($theme !== 'default' && $this->cssFileExists($file, $theme, 'horde')) {
                $urls[] = $this->buildCssUrl($file, $theme, 'horde');
            }

            // 3. App default theme (if app != horde)
            if ($app !== 'horde' && $this->cssFileExists($file, 'default', $app)) {
                $urls[] = $this->buildCssUrl($file, 'default', $app);
            }

            // 4. App selected theme (cascade over app default if different)
            if ($app !== 'horde' && $theme !== 'default' && $this->cssFileExists($file, $theme, $app)) {
                $urls[] = $this->buildCssUrl($file, $theme, $app);
            }
        }

        return $urls;
    }

    /**
     * Get list of responsive JavaScript URLs to load
     *
     * Returns URLs in cascade order with fallback to default theme:
     * 1. Horde base JS
     * 2. Application JS (if exists and app != horde)
     *
     * @param array<string> $jsFiles List of JS filenames to load
     * @param string|null $app Application name (null = current app)
     * @return array<string> Array of JS URLs
     */
    public function getJsUrls(array $jsFiles = [], ?string $app = null): array
    {
        $urls = [];
        $app ??= $this->registry->getApp();

        foreach ($jsFiles as $file) {
            // Check Horde base JS
            if ($this->jsFileExists($file, 'horde')) {
                $urls[] = $this->buildJsUrl($file, 'horde');
            }

            // Check app-specific JS (if not horde)
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
     * Checks locations in most-specific-first order:
     * 1. App selected theme:  themes/{app}/{theme}/graphics/{file}
     * 2. App default theme:   themes/{app}/default/graphics/{file}
     * 3. Horde selected theme: themes/horde/{theme}/graphics/{file}
     * 4. Horde default theme: themes/horde/default/graphics/{file}
     *
     * Returns the URI of the first match, or empty string if none found.
     *
     * @param string      $file   Graphic filename (e.g. 'new.png' or 'mime/pdf.png')
     * @param string|null $theme  Theme name (null = use preference)
     * @param string|null $app    Application name (null = current app)
     *
     * @return string  URL to the graphic, or '' if not found
     */
    public function getGraphicUrl(
        string $file,
        ?string $theme = null,
        ?string $app = null,
    ): string {
        $theme ??= $this->getThemePreference();
        $app ??= $this->registry->getApp();

        $candidates = [];

        // Most specific first — app theme override
        if ($app !== 'horde' && $theme !== 'default') {
            $candidates[] = ['theme' => $theme, 'app' => $app];
        }

        // App default theme
        if ($app !== 'horde') {
            $candidates[] = ['theme' => 'default', 'app' => $app];
        }

        // Horde selected theme
        if ($theme !== 'default') {
            $candidates[] = ['theme' => $theme, 'app' => 'horde'];
        }

        // Horde default theme (ultimate fallback)
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
        $themesUri = $this->registry->get('themesuri', $app);
        return $themesUri . '/' . $theme . '/' . $file;
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
        $jsUri = $this->registry->get('jsuri', $app);
        return $jsUri . '/' . $file;
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
            $themesFs = $this->registry->get('themesfs', $app);
            $filePath = $themesFs . '/' . $theme . '/' . $filename;
            return $this->filesystem->fileExists($filePath);
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
            $jsFs = $this->registry->get('jsfs', $app);
            $filePath = $jsFs . '/' . $filename;
            return $this->filesystem->fileExists($filePath);
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
        $themesUri = $this->registry->get('themesuri', $app);
        return $themesUri . '/' . $theme . '/graphics/' . $file;
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
            $themesFs = $this->registry->get('themesfs', $app);
            $filePath = $themesFs . '/' . $theme . '/graphics/' . $filename;
            return $this->filesystem->fileExists($filePath);
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
