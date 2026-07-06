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
use stdClass;
use Horde\Core\Factory\RegistryConfigLoaderFactory;
use Horde\Injector\Attribute\Factory;

/**
 * Registry config loader for Horde applications
 *
 * Loads global application registry from registry.php with support for:
 * - Vendor defaults (from vendor/horde/horde/config/registry.php)
 * - Deployment config (from HORDE_CONFIG_BASE/horde/registry.php)
 * - Config snippets (registry.d/)
 * - Local overrides (registry.local.php)
 * - VHost overrides (registry-{hostname}.php)
 *
 * Returns RegistryState objects, does NOT populate globals.
 *
 * Registry is global - always loads from 'horde' app only.
 *
 * ## Compiled-registry short-circuit
 *
 * If a compiled-registry file exists at $compiledRegistryPath, the
 * loader loads it via `require` and returns a merged view without
 * scanning any of the layer files above. The compiled file is
 * produced by {@see RegistryConfigCompiler} and holds a
 * two-level structure: a 'default' key with the merged default
 * registry, and one key per compiled vhost with that vhost's raw
 * delta. `load()` merges default + vhost-slot at read time.
 *
 * Callers who want to force layer reloading (e.g. after editing a
 * registry.d snippet) should delete the compiled file. `clearCache()`
 * only invalidates the in-memory RegistryState cache, not the on-disk
 * compiled file.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: RegistryConfigLoaderFactory::class, method: 'create')]
class RegistryConfigLoader
{
    private ?RegistryState $cache = null;
    private readonly string $compiledRegistryPath;

    public function __construct(
        private string $configBase,     // HORDE_CONFIG_BASE constant
        private string $vendorBase,     // Path to vendor/horde/horde directory
        private Vhost|string $vhost = 'localhost',
        ?string $compiledRegistryPath = null,
    ) {
        $this->vhost = Vhost::from($this->vhost);
        // The compiled registry lives outside the admin-authored
        // var/config/horde/ tree. It's a derived cache, not a config
        // source. Default: sibling var/cache/compiled/registry.php.
        // Callers with non-standard layouts pass this explicitly.
        $this->compiledRegistryPath = $compiledRegistryPath
            ?? dirname($this->configBase) . '/cache/compiled/registry.php';
    }

    /**
     * Load global application registry
     *
     * Registry is global - no $app parameter
     *
     * @param string $file Config filename (default: 'registry.php')
     * @return RegistryState Immutable registry state
     */
    public function load(string $file = 'registry.php'): RegistryState
    {
        if ($this->cache === null) {
            // Fast path: if a compiled registry exists, use it and
            // skip every layer file. The compiler wrote the same
            // merge result the layer scan would produce, so callers
            // observe identical output either way.
            //
            // Only the default filename is served from the compiled
            // artifact. Callers passing an explicit non-default $file
            // want a different registry (e.g. a test fixture) and
            // must fall through to the layer scan.
            if ($file === 'registry.php'
                && file_exists($this->compiledRegistryPath)
            ) {
                $applications = $this->loadFromCompiled();
            } else {
                $applications = $this->loadFiles($file);
            }
            $this->cache = new RegistryState($applications);
        }

        return $this->cache;
    }

    /**
     * Load specific layer for introspection
     *
     * @param string $layer Layer name ('vendor', 'base', 'snippets', 'local', 'vhost')
     * @param string $file Config filename
     * @return array Raw applications array from that layer
     */
    public function loadLayer(string $layer, string $file = 'registry.php'): array
    {
        $pathInfo = pathinfo($file);
        $confDir = $this->configBase . '/horde/';
        $vendorDir = $this->vendorBase . '/config/';

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
        $this->cache = null;
    }

    /**
     * Load and merge registry files
     *
     * @param string $file Config filename
     * @return array Merged applications array
     */
    private function loadFiles(string $file): array
    {
        $pathInfo = pathinfo($file);
        $confDir = $this->configBase . '/horde/';
        $vendorDir = $this->vendorBase . '/config/';

        // Build file list
        $files = [];

        // 1. Vendor defaults (factory defaults)
        $files[] = $vendorDir . $file;

        // 2. Deployment base config
        $files[] = $confDir . $file;

        // 3. Config snippets (registry.d/)
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
        $applications = [];

        foreach ($files as $filePath) {
            if (file_exists($filePath)) {
                // Include file in isolated scope
                $loaded = $this->includeFile($filePath);

                // Merge applications
                $applications = array_replace_recursive($applications, $loaded);
            }
        }

        // 5. VHost override (via Vhost object)
        if ($this->vhost->isAvailable()) {
            $vhostFilename = $this->vhost->getVhostFilename($file);
            if ($vhostFilename) {
                $vhostFile = $confDir . $vhostFilename;
                if (file_exists($vhostFile)) {
                    $loaded = $this->includeFile($vhostFile);
                    $applications = array_replace_recursive($applications, $loaded);
                }
            }
        }

        return $applications;
    }

    /**
     * Load the merged registry from a pre-compiled artifact.
     *
     * The compiled file is a plain PHP `<?php return [...]` returning
     * the two-level structure produced by RegistryConfigCompiler:
     *   [
     *     'default' => [ merged default registry ],
     *     '<vhost>' => [ vhost-specific delta ],
     *   ]
     *
     * If the current vhost is present in the compiled artifact, its
     * delta merges onto the default via array_replace_recursive (same
     * semantics as the layer scan). Vhosts not present in the compiled
     * artifact fall back to the default silently. This matches the
     * layer scan's behavior when no registry-{vhost}.php file exists.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadFromCompiled(): array
    {
        $compiled = require $this->compiledRegistryPath;

        // Defensive: if the compiled file is somehow malformed (not an
        // array, or missing 'default'), fall back to layers rather
        // than propagate an unusable state. This preserves the "you
        // can delete the compiled file to force a fresh scan" story
        // even when the file is corrupt.
        if (!is_array($compiled) || !isset($compiled['default'])) {
            return $this->loadFiles('registry.php');
        }

        $applications = $compiled['default'];

        if ($this->vhost->isAvailable()) {
            $hostname = $this->vhost->getHostname();
            if ($hostname !== null && isset($compiled[$hostname])) {
                $applications = array_replace_recursive(
                    $applications,
                    $compiled[$hostname]
                );
            }
        }

        return $applications;
    }

    /**
     * Include PHP file in isolated scope with $this context.
     *
     * Registry files expect $this->applications to be available
     *
     * @param string $file File path
     * @return array Extracted applications array
     */
    private function includeFile(string $file): array
    {
        // Create mock object with applications property
        // Registry files assign to $this->applications
        $mock = new class {
            public array $applications = [];
        };

        // Execute file with $this pointing to mock
        $executeInContext = function ($file) use ($mock) {
            // Bind closure to mock object so $this is available
            $executor = function () use ($file) {
                include $file;
            };
            $boundExecutor = $executor->bindTo($mock, $mock);
            $boundExecutor();
        };

        $executeInContext($file);

        return $mock->applications;
    }
}
