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
 * Config loader for Horde applications
 *
 * Loads configuration from HORDE_CONFIG_BASE with support for:
 * - Config snippets (conf.d/)
 * - Local overrides (conf.local.php)
 * - VHost overrides (conf-{hostname}.php)
 *
 * Returns ConfigState objects, does NOT populate globals.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ConfigLoader
{
    private array $cache = [];

    public function __construct(
        private string $configBase,  // HORDE_CONFIG_BASE value
        private Vhost|string $vhost = 'localhost'
    ) {
        $this->vhost = Vhost::from($this->vhost);
    }

    /**
     * Load config for an app
     *
     * @param string $app App name (e.g., 'horde', 'imp', 'turba')
     * @param string $file Config filename (default: 'conf.php')
     * @return State Immutable config state
     */
    public function load(string $app, string $file = 'conf.php'): State
    {
        $cacheKey = $app . ':' . $file;

        if (!isset($this->cache[$cacheKey])) {
            $config = $this->loadFiles($app, $file);
            $this->cache[$cacheKey] = new State($config);
        }

        return $this->cache[$cacheKey];
    }

    /**
     * Load and merge config files
     *
     * @param string $app App name
     * @param string $file Config filename
     * @return array Merged config array
     */
    private function loadFiles(string $app, string $file): array
    {
        $pathInfo = pathinfo($file);
        $confDir = $this->configBase . '/' . $app . '/';

        // Build file list
        $files = [];

        // 1. Main config
        $files[] = $confDir . $file;

        // 2. Config snippets (conf.d/)
        $confDDir = $confDir . $pathInfo['filename'] . '.d';
        if (is_dir($confDDir)) {
            $snippets = glob($confDDir . '/*.php');
            if ($snippets !== false) {
                sort($snippets);  // Load in alphabetical order
                $files = array_merge($files, $snippets);
            }
        }

        // 3. Local override
        $localFile = $confDir . $pathInfo['filename'] . '.local.' . $pathInfo['extension'];
        $files[] = $localFile;

        // Load files
        $conf = [];

        foreach ($files as $filePath) {
            if (file_exists($filePath)) {
                // Include file in isolated scope
                $loadedVars = $this->includeFile($filePath);

                // Merge $conf array
                if (isset($loadedVars['conf'])) {
                    $conf = array_replace_recursive($conf, $loadedVars['conf']);
                }
            }
        }

        // 4. VHost override (via Vhost object)
        if ($this->vhost->isAvailable()) {
            $vhostFilename = $this->vhost->getVhostFilename($file);
            if ($vhostFilename) {
                $vhostFile = $confDir . $vhostFilename;
                if (file_exists($vhostFile)) {
                    $loadedVars = $this->includeFile($vhostFile);
                    if (isset($loadedVars['conf'])) {
                        $conf = array_replace_recursive($conf, $loadedVars['conf']);
                    }
                }
            }
        }

        return $conf;
    }

    /**
     * Include PHP file in isolated scope and extract variables
     *
     * @param string $file File path
     * @return array Extracted variables
     */
    private function includeFile(string $file): array
    {
        // Define variables that config files expect
        $conf = [];

        // Include file
        include $file;

        // Return all defined variables
        return get_defined_vars();
    }

    /**
     * Clear cache (useful for testing)
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
