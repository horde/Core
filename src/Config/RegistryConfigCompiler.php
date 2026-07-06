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
 * Compile all registry sources into a single array graph.
 *
 * Produces the two-level structure consumed by RegistryConfigLoader's
 * compiled-registry short-circuit:
 *
 *   [
 *     'default'         => [ ...merged default registry, per-app array ],
 *     'foo.example.com' => [ ...delta relative to default              ],
 *     'bar.example.com' => [ ...delta relative to default              ],
 *   ]
 *
 * The 'default' slot is the fully-merged result of the standard load
 * chain minus vhost. Vendor defaults, deployment base, registry.d
 * snippets and registry.local.php get merged with
 * array_replace_recursive at each step. This matches what
 * RegistryConfigLoader::load() returns to a caller with no vhost.
 *
 * Each vhost slot holds only the delta: the raw contents of the
 * corresponding registry-{hostname}.php file. Storing the delta rather
 * than a fully-merged view keeps the compiled artifact small. For a
 * dozen vhosts that share the same base registry, the compiled file
 * grows by roughly one delta per vhost rather than N full registries.
 * RegistryConfigLoader recombines default + vhost-slot at read time.
 *
 * ## Vhost discovery
 *
 * If no explicit vhost list is supplied, the compiler discovers vhosts
 * by globbing `{configBase}/horde/registry-*.php`. Extracts the vhost
 * name from the filename. This is the common case. A deployment's
 * vhost set is defined by which vhost files exist.
 *
 * Callers with an external source of truth for the vhost list (a
 * deployment manifest, a database, a config array) can pass `$vhosts`
 * explicitly. The compiler then only reads vhost files for the listed
 * names, and silently skips names whose files don't exist.
 *
 * ## Merge semantics
 *
 * Uses `array_replace_recursive`, matching RegistryConfigLoader. Legacy
 * Horde_Registry used bare `include` (which is shallow-replacement at
 * the top level of $this->applications). Modern uses recursive. The
 * compiler follows modern.
 *
 * ## Not written to disk by the compiler
 *
 * `compile()` returns an in-memory array. Callers that want to
 * persist it use {@see RegistryConfigWriter}, which handles atomic
 * write and opcache invalidation as separate concerns. The
 * compiler stays a pure-compute class with no filesystem side
 * effects. Deterministic, testable and safe to invoke from any
 * context (CLI, HTTP request, unit test) without worrying about
 * disk state.
 *
 * The split is intentional: serialization format, atomic write,
 * permission handling and opcache invalidation are operational
 * concerns that vary by caller (installer vs. hordectl vs. CI
 * pipeline). Baking them into the compiler would force every
 * caller through one policy.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class RegistryConfigCompiler
{
    /**
     * @param string $configBase Deployment config root (HORDE_CONFIG_BASE).
     *                           The compiler reads
     *                           `{configBase}/horde/registry.php`,
     *                           `registry.d/*.php`,
     *                           `registry.local.php` and
     *                           `registry-{vhost}.php` files.
     * @param string $vendorBase Path to the vendor/horde/horde
     *                           directory. The compiler reads
     *                           `{vendorBase}/config/registry.php` as
     *                           the factory defaults.
     * @param string[]|null $vhosts Explicit vhost list, or null for
     *                              auto-discovery via filesystem glob.
     *                              Pass an empty array for a
     *                              default-only compile.
     */
    public function __construct(
        private readonly string $configBase,
        private readonly string $vendorBase,
        private readonly ?array $vhosts = null,
    ) {}

    /**
     * Compile the full registry graph.
     *
     * @return array{
     *     default: array<string, array<string, mixed>>,
     * } & array<string, array<string, array<string, mixed>>>
     *     A map with the 'default' key holding the merged default
     *     registry, plus one key per compiled vhost holding that
     *     vhost's raw delta. Vhosts whose files don't exist are
     *     silently omitted from the result.
     */
    public function compile(): array
    {
        $result = ['default' => $this->compileDefault()];

        $vhosts = $this->vhosts ?? $this->discoverVhosts();
        foreach ($vhosts as $vhost) {
            $delta = $this->loadVhostDelta($vhost);
            if ($delta !== null) {
                $result[$vhost] = $delta;
            }
        }

        return $result;
    }

    /**
     * Merge the four non-vhost layers into a single applications array.
     *
     * Order (each layer overrides the previous via
     * array_replace_recursive):
     *   1. vendor defaults: {vendorBase}/config/registry.php
     *   2. deployment base: {configBase}/horde/registry.php
     *   3. snippets:        {configBase}/horde/registry.d/*.php (sorted)
     *   4. local override:  {configBase}/horde/registry.local.php
     *
     * @return array<string, array<string, mixed>>
     */
    private function compileDefault(): array
    {
        $confDir = $this->configBase . '/horde/';
        $vendorDir = $this->vendorBase . '/config/';

        $files = [];

        // Layer 1: vendor defaults.
        $files[] = $vendorDir . 'registry.php';

        // Layer 2: deployment base.
        $files[] = $confDir . 'registry.php';

        // Layer 3: snippets. Sorted so numeric prefixes (01-, 02-, ...)
        // load in the intended order across filesystems.
        $snippetsDir = $confDir . 'registry.d';
        if (is_dir($snippetsDir)) {
            $snippets = glob($snippetsDir . '/*.php');
            if ($snippets !== false) {
                sort($snippets);
                $files = array_merge($files, $snippets);
            }
        }

        // Layer 4: local override.
        $files[] = $confDir . 'registry.local.php';

        $applications = [];
        foreach ($files as $file) {
            if (file_exists($file)) {
                $applications = array_replace_recursive(
                    $applications,
                    $this->includeFile($file)
                );
            }
        }

        return $applications;
    }

    /**
     * Read a single vhost file and return its raw applications delta.
     *
     * Returns null (not an empty array) when the file doesn't exist,
     * so callers can distinguish "no vhost file" from "vhost file with
     * no applications". The latter should still be recorded as a
     * present-but-empty override.
     *
     * @return array<string, array<string, mixed>>|null
     */
    private function loadVhostDelta(string $vhost): ?array
    {
        $vhostFile = $this->configBase . '/horde/registry-' . $vhost . '.php';
        if (!file_exists($vhostFile)) {
            return null;
        }
        return $this->includeFile($vhostFile);
    }

    /**
     * Discover vhosts by scanning for registry-*.php files.
     *
     * Returns hostnames extracted from filenames, unsorted. Sorting is
     * not meaningful for vhost slots. They are keyed by hostname in
     * the output, and no vhost merges onto another.
     *
     * @return string[]
     */
    private function discoverVhosts(): array
    {
        $pattern = $this->configBase . '/horde/registry-*.php';
        $files = glob($pattern);
        if ($files === false) {
            return [];
        }

        $vhosts = [];
        foreach ($files as $file) {
            $basename = basename($file);
            // strip 'registry-' prefix (9 chars) and '.php' suffix.
            $vhost = substr($basename, 9, -4);
            // Guard against pathological empty match. Theoretically
            // impossible from the glob, but a defensive skip is cheap.
            if ($vhost !== '') {
                $vhosts[] = $vhost;
            }
        }

        return $vhosts;
    }

    /**
     * Include a registry file with $this->applications context.
     *
     * Registry files assign to $this->applications['app'] = [...]. To
     * make that work without polluting the compiler's own state, bind
     * the include to a disposable object whose only public property is
     * $applications, then read that property back after include
     * returns.
     *
     * Duplicated from RegistryConfigLoader::includeFile intentionally.
     * Both callers do the same thing, extraction would create a new
     * public helper class for 22 lines of shared logic, and the
     * duplication risk is low because the file-format contract is
     * stable.
     *
     * @return array<string, array<string, mixed>>
     */
    private function includeFile(string $file): array
    {
        $mock = new class {
            /** @var array<string, array<string, mixed>> */
            public array $applications = [];
        };

        $executor = function () use ($file) {
            include $file;
        };
        $executor->bindTo($mock, $mock)();

        return $mock->applications;
    }
}
