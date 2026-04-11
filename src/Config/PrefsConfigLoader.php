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
use Closure;

/**
 * Prefs config loader for Horde applications
 *
 * Loads preference definitions from prefs.php with support for:
 * - Vendor defaults (from vendor/horde/$app/config/prefs.php)
 * - Deployment config (from HORDE_CONFIG_BASE/$app/prefs.php)
 * - Config snippets (prefs.d/)
 * - Local overrides (prefs.local.php)
 * - VHost overrides (prefs-{hostname}.php)
 *
 * Returns PrefsState objects, does NOT populate globals.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class PrefsConfigLoader
{
    private array $cache = [];

    public function __construct(
        private string $configBase,     // HORDE_CONFIG_BASE constant
        private string $vendorBase,     // Path to vendor/horde/ directory
        private Vhost|string $vhost = 'localhost'
    ) {
        $this->vhost = Vhost::from($this->vhost);
    }

    /**
     * Load preference definitions for an app
     *
     * @param string $app App name ('horde', 'turba', 'imp', etc.)
     * @param string $file Config filename (default: 'prefs.php')
     * @return PrefsState Immutable prefs state
     */
    public function load(string $app, string $file = 'prefs.php'): PrefsState
    {
        $cacheKey = $app . ':' . $file;

        if (!isset($this->cache[$cacheKey])) {
            $config = $this->loadFiles($app, $file);
            $this->cache[$cacheKey] = new PrefsState(
                $config['_prefs'] ?? [],
                $config['prefGroups'] ?? []
            );
        }

        return $this->cache[$cacheKey];
    }

    /**
     * Load specific layer for introspection
     *
     * @param string $app App name
     * @param string $layer Layer name ('vendor', 'base', 'snippets', 'local', 'vhost')
     * @param string $file Config filename
     * @return array Raw config from that layer (['_prefs' => [...], 'prefGroups' => [...]])
     */
    public function loadLayer(string $app, string $layer, string $file = 'prefs.php'): array
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
                $merged = ['_prefs' => [], 'prefGroups' => []];
                foreach ($snippets as $snippetFile) {
                    $loaded = $this->includeFile($snippetFile);
                    $merged = $this->mergePrefs($merged, $loaded);
                }
                return $merged;

            case 'local':
                $filePath = $confDir . $pathInfo['filename'] . '.local.' . $pathInfo['extension'];
                return file_exists($filePath) ? $this->includeFile($filePath) : [];

            case 'vhost':
                if (!$this->vhost->isAvailable()) {
                    return [];
                }
                $vhostFilename = $this->vhost->getVhostFilename($file);
                if (!$vhostFilename) {
                    return [];
                }
                $vhostFile = $confDir . $vhostFilename;
                return file_exists($vhostFile) ? $this->includeFile($vhostFile) : [];

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
     * Load and merge prefs files
     *
     * @param string $app App name
     * @param string $file Config filename
     * @return array Merged config ['_prefs' => [...], 'prefGroups' => [...]]
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

        // 3. Config snippets (prefs.d/)
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
        $config = ['_prefs' => [], 'prefGroups' => []];

        foreach ($files as $filePath) {
            if (file_exists($filePath)) {
                // Include file in isolated scope
                $loaded = $this->includeFile($filePath);

                // Merge prefs and groups
                $config = $this->mergePrefs($config, $loaded);
            }
        }

        // 5. VHost override (via Vhost object)
        if ($this->vhost->isAvailable()) {
            $vhostFilename = $this->vhost->getVhostFilename($file);
            if ($vhostFilename) {
                $vhostFile = $confDir . $vhostFilename;
                if (file_exists($vhostFile)) {
                    $loaded = $this->includeFile($vhostFile);
                    $config = $this->mergePrefs($config, $loaded);
                }
            }
        }

        return $config;
    }

    /**
     * Merge prefs configuration preserving closures
     *
     * @param array $existing Existing config
     * @param array $new New config to merge
     * @return array Merged config
     */
    private function mergePrefs(array $existing, array $new): array
    {
        // Merge $_prefs
        if (isset($new['_prefs'])) {
            foreach ($new['_prefs'] as $key => $value) {
                if (isset($existing['_prefs'][$key]) && is_array($existing['_prefs'][$key]) && is_array($value)) {
                    // Merge pref definitions
                    // Special handling: closures cannot be merged, keep first one
                    foreach ($value as $prefKey => $prefValue) {
                        if ($prefValue instanceof Closure) {
                            // Keep existing closure if present, otherwise use new
                            if (!isset($existing['_prefs'][$key][$prefKey]) || !($existing['_prefs'][$key][$prefKey] instanceof Closure)) {
                                $existing['_prefs'][$key][$prefKey] = $prefValue;
                            }
                        } else {
                            // Normal value, merge
                            $existing['_prefs'][$key][$prefKey] = $prefValue;
                        }
                    }
                } else {
                    // Replace entire pref
                    $existing['_prefs'][$key] = $value;
                }
            }
        }

        // Merge prefGroups
        if (isset($new['prefGroups'])) {
            $existing['prefGroups'] = array_replace_recursive(
                $existing['prefGroups'] ?? [],
                $new['prefGroups']
            );
        }

        return $existing;
    }

    /**
     * Include PHP file in isolated scope and extract variables
     *
     * @param string $file File path
     * @return array Extracted config ['_prefs' => [...], 'prefGroups' => [...]]
     */
    private function includeFile(string $file): array
    {
        // Define variables that config files expect
        $_prefs = [];
        $prefGroups = [];

        // Include file
        include $file;

        // Return extracted variables
        return [
            '_prefs' => $_prefs,
            'prefGroups' => $prefGroups,
        ];
    }
}
