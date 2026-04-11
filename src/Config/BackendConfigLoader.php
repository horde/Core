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

namespace Horde\Core\Config;

use RuntimeException;

/**
 * Backend config loader for Horde applications
 *
 * Loads backend definitions from backends.php with support for:
 * - Vendor defaults (from vendor/horde/$app/config/backends.php)
 * - Deployment config (from HORDE_CONFIG_BASE/$app/backends.php)
 * - Config snippets (backends.d/)
 * - Local overrides (backends.local.php)
 * - VHost overrides (backends-{hostname}.php)
 *
 * Returns BackendState objects, does NOT populate globals.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class BackendConfigLoader
{
    private array $cache = [];

    public function __construct(
        private string $configBase,  // HORDE_CONFIG_BASE constant
        private string $vendorBase,  // Path to vendor/horde/ directory
        private Vhost|string $vhost = 'localhost'
    ) {
        $this->vhost = Vhost::from($this->vhost);
    }

    /**
     * Load backend definitions for an app
     *
     * @param string $app App name ('passwd', 'imp', 'ingo', etc.)
     * @param string $file Config filename (default: 'backends.php')
     * @return BackendState Immutable backend state
     */
    public function load(string $app, string $file = 'backends.php'): BackendState
    {
        $cacheKey = $app . ':' . $file;

        if (!isset($this->cache[$cacheKey])) {
            $backends = $this->loadFiles($app, $file);
            $this->cache[$cacheKey] = new BackendState($backends);
        }

        return $this->cache[$cacheKey];
    }

    /**
     * Load specific layer for introspection
     *
     * @param string $app App name
     * @param string $layer Layer name ('vendor', 'base', 'snippets', 'local', 'vhost')
     * @param string $file Config filename
     * @return array Raw config from that layer only
     */
    public function loadLayer(string $app, string $layer, string $file = 'backends.php'): array
    {
        $pathInfo = pathinfo($file);
        $confDir = $this->configBase . '/' . $app . '/';
        $vendorDir = $this->vendorBase . '/' . $app . '/config/';

        switch ($layer) {
            case 'vendor':
                $filePath = $vendorDir . $file;
                return file_exists($filePath) ? $this->includeFile($filePath) : [];

            case 'base':
                $filePath = $confDir . $file;
                return file_exists($filePath) ? $this->includeFile($filePath) : [];

            case 'snippets':
                $snippetsDir = $confDir . $pathInfo['filename'] . '.d';
                if (!is_dir($snippetsDir)) {
                    return [];
                }
                $snippets = glob($snippetsDir . '/*.php');
                if ($snippets === false) {
                    return [];
                }
                sort($snippets);
                $merged = [];
                foreach ($snippets as $snippetFile) {
                    $loaded = $this->includeFile($snippetFile);
                    $merged = array_replace_recursive($merged, $loaded);
                }
                return $merged;

            case 'local':
                $filePath = $confDir . $pathInfo['filename'] . '.local.' . $pathInfo['extension'];
                return file_exists($filePath) ? $this->includeFile($filePath) : [];

            case 'vhost':
                // Need to check if vhosts enabled - requires loading base config first
                // For introspection, just return empty if vhost file doesn't exist
                $vhostFile = $confDir . $pathInfo['filename'] . '-*.' . $pathInfo['extension'];
                $vhostFiles = glob($vhostFile);
                if ($vhostFiles === false || empty($vhostFiles)) {
                    return [];
                }
                // Return first vhost file found (for introspection purposes)
                return $this->includeFile($vhostFiles[0]);

            default:
                throw new RuntimeException("Unknown layer: $layer");
        }
    }

    /**
     * Clear cache (useful for testing)
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Load and merge backend files
     *
     * @param string $app App name
     * @param string $file Config filename
     * @return array Merged backends array
     */
    private function loadFiles(string $app, string $file): array
    {
        $pathInfo = pathinfo($file);
        $confDir = $this->configBase . '/' . $app . '/';
        $vendorDir = $this->vendorBase . '/' . $app . '/config/';

        // Build file list
        $files = [];

        // 1. Vendor defaults (factory defaults)
        $files[] = $vendorDir . $file;

        // 2. Deployment base config
        $files[] = $confDir . $file;

        // 3. Config snippets (backends.d/)
        $snippetsDir = $confDir . $pathInfo['filename'] . '.d';
        if (is_dir($snippetsDir)) {
            $snippets = glob($snippetsDir . '/*.php');
            if ($snippets !== false) {
                sort($snippets);  // Load in alphabetical order
                $files = array_merge($files, $snippets);
            }
        }

        // 4. Local override
        $localFile = $confDir . $pathInfo['filename'] . '.local.' . $pathInfo['extension'];
        $files[] = $localFile;

        // Load files
        $backends = [];

        foreach ($files as $filePath) {
            if (file_exists($filePath)) {
                // Include file in isolated scope
                $loadedBackends = $this->includeFile($filePath);

                // Merge backends array
                $backends = array_replace_recursive($backends, $loadedBackends);
            }
        }

        // 5. VHost override (via Vhost object)
        if ($this->vhost->isAvailable()) {
            $vhostFilename = $this->vhost->getVhostFilename($file);
            if ($vhostFilename) {
                $vhostFile = $confDir . $vhostFilename;
                if (file_exists($vhostFile)) {
                    $loadedBackends = $this->includeFile($vhostFile);
                    $backends = array_replace_recursive($backends, $loadedBackends);
                }
            }
        }

        return $backends;
    }

    /**
     * Include PHP file in isolated scope and extract $backends variable
     *
     * @param string $file File path
     * @return array Extracted backends array
     */
    private function includeFile(string $file): array
    {
        // Define variables that config files expect
        $backends = [];

        // Include file
        include $file;

        // Return $backends variable
        return $backends;
    }
}
