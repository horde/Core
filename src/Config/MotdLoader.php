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

/**
 * Message-of-the-Day (MOTD) loader for Horde applications
 *
 * Loads the MOTD snippet that is shown below the login form, with the same
 * layered discovery as BackendConfigLoader:
 * - Vendor default (from vendor/horde/$app/config/motd.php)
 * - Deployment config (from HORDE_CONFIG_BASE/$app/motd.php)
 * - Config snippets (motd.d/)
 * - Local overrides (motd.local.php)
 * - VHost overrides (motd-{hostname}.php)
 *
 * Unlike BackendConfigLoader, the MOTD config files produce an HTML *string*
 * (traditionally via output buffering into a $motd variable) rather than an
 * array, so layers are concatenated instead of array-merged.
 *
 * The returned HTML is trusted administrator content and is echoed raw by the
 * login template by design.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class MotdLoader
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
     * Load and concatenate the MOTD HTML for an app
     *
     * @param string $app App name (default: 'horde').
     * @param string $file Config filename (default: 'motd.php').
     * @param string $variable Name of the string variable the config file
     *                         populates, without the '$' (default: 'motd').
     * @return string MOTD HTML, or '' if no MOTD is configured.
     */
    public function load(string $app = 'horde', string $file = 'motd.php', string $variable = 'motd'): string
    {
        $cacheKey = $app . ':' . $file . ':' . $variable;

        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->loadFiles($app, $file, $variable);
        }

        return $this->cache[$cacheKey];
    }

    /**
     * Clear cache (useful for testing)
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Load and concatenate MOTD files across all layers
     *
     * @param string $app App name
     * @param string $file Config filename
     * @param string $variable Name of the string variable the config file
     *                         populates, without the '$'.
     * @return string Concatenated MOTD HTML
     */
    private function loadFiles(string $app, string $file, string $variable): string
    {
        $pathInfo = pathinfo($file);
        $confDir = $this->configBase . '/' . $app . '/';
        $vendorDir = $this->vendorBase . '/' . $app . '/config/';

        // Build file list, in increasing order of precedence.
        $files = [];

        // 1. Vendor defaults (factory defaults)
        $files[] = $vendorDir . $file;

        // 2. Deployment base config
        $files[] = $confDir . $file;

        // 3. Config snippets (motd.d/)
        $snippetsDir = $confDir . $pathInfo['filename'] . '.d';
        if (is_dir($snippetsDir)) {
            $snippets = glob($snippetsDir . '/*.php');
            if ($snippets !== false) {
                sort($snippets);  // Load in alphabetical order
                $files = array_merge($files, $snippets);
            }
        }

        // 4. Local override
        $files[] = $confDir . $pathInfo['filename'] . '.local.' . $pathInfo['extension'];

        // 5. VHost override (via Vhost object)
        if ($this->vhost->isAvailable()) {
            $vhostFilename = $this->vhost->getVhostFilename($file);
            if ($vhostFilename) {
                $files[] = $confDir . $vhostFilename;
            }
        }

        // Load and concatenate.
        $motd = '';
        foreach ($files as $filePath) {
            if (file_exists($filePath)) {
                $motd .= $this->includeFile($filePath, $variable);
            }
        }

        return $motd;
    }

    /**
     * Include a MOTD config file in an isolated scope and return its HTML.
     *
     * MOTD files come in two conventions: the shipped default captures its
     * output into the named variable itself (ob_start() ... $motd =
     * ob_get_clean();), while older/hand-written files simply echo markup.
     * To support both, the include runs inside an output buffer: if the file
     * set the named variable we use that, otherwise we fall back to whatever
     * it echoed.
     *
     * @param string $file     File path
     * @param string $variable Variable name to read, without the '$'.
     * @return string MOTD HTML produced by this file.
     */
    private function includeFile(string $file, string $variable): string
    {
        // Predefine the expected variable so a file that only echoes yields ''.
        $$variable = '';

        ob_start();
        include $file;
        $captured = (string) ob_get_clean();

        return ($$variable !== '') ? (string) $$variable : $captured;
    }
}
