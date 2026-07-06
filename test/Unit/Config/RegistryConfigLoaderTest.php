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

namespace Horde\Core\Test\Unit\Config;

use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Config\RegistryState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RegistryConfigLoader.
 *
 * RegistryConfigLoader loads global application registry from registry.php
 * files. Similar to PrefsConfigLoader but:
 * - Always loads from 'horde' app (global registry)
 * - Uses $this->applications context binding
 * - Single cache instance (not keyed by app)
 * - Simple array_replace_recursive merge (no closure handling)
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(RegistryConfigLoader::class)]
class RegistryConfigLoaderTest extends TestCase
{
    private string $tempDir;
    private string $vendorDir;
    private string $configDir;
    private RegistryConfigLoader $loader;

    protected function setUp(): void
    {
        // Create temp directory structure
        $this->tempDir = sys_get_temp_dir() . '/horde-registry-test-' . uniqid();
        $this->vendorDir = $this->tempDir . '/vendor/horde/horde';
        $this->configDir = $this->tempDir . '/config';

        mkdir($this->vendorDir . '/config', 0o755, true);
        mkdir($this->configDir . '/horde', 0o755, true);

        $this->loader = new RegistryConfigLoader($this->configDir, $this->vendorDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ===================================================================
    // A. Load Layers (5 tests - one per layer)
    // ===================================================================

    public function testLoadVendorDefaults(): void
    {
        // Create vendor defaults with $this->applications syntax
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['horde'] = [
                    'name' => 'Horde',
                    'status' => 'active',
                    'provides' => 'horde',
                    'webroot' => '/horde',
                    'fileroot' => '/var/www/horde',
                    'jsuri' => '/horde/js',
                    'themesuri' => '/horde/themes',
                    'staticuri' => '/horde/static',
                ];
                $this->applications['imp'] = [
                    'name' => 'IMP',
                    'status' => 'inactive',
                    'provides' => 'mail',
                ];
                PHP
        );

        $state = $this->loader->load();

        $this->assertInstanceOf(RegistryState::class, $state);
        $apps = $state->toArray();
        $this->assertArrayHasKey('horde', $apps);
        $this->assertArrayHasKey('imp', $apps);
        $this->assertSame('Horde', $apps['horde']['name']);
    }

    public function testLoadDeploymentBaseConfig(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['imp'] = [
                    'name' => 'IMP',
                    'status' => 'inactive',
                    'webroot' => '/imp',
                    'fileroot' => '/var/www/imp',
                    'jsuri' => '/imp/js',
                    'themesuri' => '/imp/themes',
                ];
                PHP
        );

        // Base config overrides status
        $baseFile = $this->configDir . '/horde/registry.php';
        file_put_contents(
            $baseFile,
            <<<'PHP'
                <?php
                $this->applications['imp'] = [
                    'status' => 'active',
                ];
                PHP
        );

        $state = $this->loader->load();
        $apps = $state->toArray();

        // Base overrides status
        $this->assertSame('active', $apps['imp']['status']);
        // Name preserved from vendor
        $this->assertSame('IMP', $apps['imp']['name']);
    }

    public function testLoadConfigSnippets(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['horde'] = [
                    'name' => 'Horde',
                ];
                PHP
        );

        // Create snippets directory
        mkdir($this->configDir . '/horde/registry.d', 0o755, true);

        // Snippet 1 adds IMP
        $snippet1 = $this->configDir . '/horde/registry.d/01-imp.php';
        file_put_contents(
            $snippet1,
            <<<'PHP'
                <?php
                $this->applications['imp'] = [
                    'name' => 'IMP',
                    'provides' => 'mail',
                ];
                PHP
        );

        // Snippet 2 adds Turba
        $snippet2 = $this->configDir . '/horde/registry.d/02-turba.php';
        file_put_contents(
            $snippet2,
            <<<'PHP'
                <?php
                $this->applications['turba'] = [
                    'name' => 'Turba',
                    'provides' => 'contacts',
                ];
                PHP
        );

        $state = $this->loader->load();
        $apps = $state->toArray();

        // All apps loaded
        $this->assertArrayHasKey('horde', $apps);
        $this->assertArrayHasKey('imp', $apps);
        $this->assertArrayHasKey('turba', $apps);
    }

    public function testLoadLocalOverrides(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['imp'] = [
                    'name' => 'IMP',
                    'status' => 'inactive',
                    'webroot' => '/imp',
                    'fileroot' => '/var/www/imp',
                    'jsuri' => '/imp/js',
                    'themesuri' => '/imp/themes',
                ];
                PHP
        );

        // Local override
        $localFile = $this->configDir . '/horde/registry.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $this->applications['imp'] = [
                    'status' => 'active',
                ];
                PHP
        );

        $state = $this->loader->load();
        $apps = $state->toArray();

        // Local overrides status
        $this->assertSame('active', $apps['imp']['status']);
        // But preserves name and webroot from vendor
        $this->assertSame('IMP', $apps['imp']['name']);
        $this->assertSame('/imp', $apps['imp']['webroot']);
    }

    public function testLoadVhostOverrides(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['imp'] = [
                    'name' => 'IMP',
                    'webroot' => '/imp',
                ];
                PHP
        );

        // VHost override for example.com
        $vhostFile = $this->configDir . '/horde/registry-example.com.php';
        file_put_contents(
            $vhostFile,
            <<<'PHP'
                <?php
                $this->applications['imp'] = [
                    'webroot' => '/mail',
                ];
                PHP
        );

        // Create loader with hostname
        $vhostLoader = new RegistryConfigLoader(
            $this->configDir,
            $this->vendorDir,
            'example.com'
        );

        $state = $vhostLoader->load();
        $apps = $state->toArray();

        // VHost overrides webroot
        $this->assertSame('/mail', $apps['imp']['webroot']);
        // Name preserved from vendor
        $this->assertSame('IMP', $apps['imp']['name']);
    }

    // ===================================================================
    // B. Context Binding (3 tests - UNIQUE to Registry)
    // ===================================================================

    public function testContextBindingWithApplicationsProperty(): void
    {
        // Registry files use $this->applications
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                // This syntax must work
                $this->applications['horde'] = [
                    'name' => 'Horde',
                ];
                PHP
        );

        $state = $this->loader->load();
        $apps = $state->toArray();

        $this->assertArrayHasKey('horde', $apps);
        $this->assertSame('Horde', $apps['horde']['name']);
    }

    public function testMultipleAssignmentsToApplications(): void
    {
        // Registry files can assign multiple apps in one file
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['horde'] = ['name' => 'Horde'];
                $this->applications['imp'] = ['name' => 'IMP'];
                $this->applications['turba'] = ['name' => 'Turba'];
                PHP
        );

        $state = $this->loader->load();
        $apps = $state->toArray();

        $this->assertCount(3, $apps);
        $this->assertArrayHasKey('horde', $apps);
        $this->assertArrayHasKey('imp', $apps);
        $this->assertArrayHasKey('turba', $apps);
    }

    public function testContextIsolationBetweenFiles(): void
    {
        // Vendor file
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['horde'] = ['name' => 'Horde'];
                PHP
        );

        // Local file adds another app
        $localFile = $this->configDir . '/horde/registry.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $this->applications['imp'] = ['name' => 'IMP'];
                PHP
        );

        $state = $this->loader->load();
        $apps = $state->toArray();

        // Both apps present (contexts were isolated but results merged)
        $this->assertCount(2, $apps);
    }

    // ===================================================================
    // C. Single Cache Instance (2 tests)
    // ===================================================================

    public function testMultipleLoadsReturnSameInstance(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['horde'] = ['name' => 'Horde'];
                PHP
        );

        $state1 = $this->loader->load();
        $state2 = $this->loader->load();

        // Same instance returned (cached)
        $this->assertSame($state1, $state2);
    }

    public function testClearCacheInvalidatesCache(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['horde'] = ['name' => 'Horde'];
                PHP
        );

        $state1 = $this->loader->load();
        $this->loader->clearCache();
        $state2 = $this->loader->load();

        // Different instances after cache clear
        $this->assertNotSame($state1, $state2);
        // But same data
        $this->assertSame($state1->toArray(), $state2->toArray());
    }

    // ===================================================================
    // D. Merge Behavior (2 tests)
    // ===================================================================

    public function testArrayReplaceRecursiveMerge(): void
    {
        // Vendor defaults with nested structure
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['imp'] = [
                    'name' => 'IMP',
                    'params' => [
                        'timeout' => 30,
                        'ssl' => false,
                    ],
                ];
                PHP
        );

        // Local override changes only timeout
        $localFile = $this->configDir . '/horde/registry.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $this->applications['imp'] = [
                    'params' => [
                        'timeout' => 60,
                    ],
                ];
                PHP
        );

        $state = $this->loader->load();
        $apps = $state->toArray();

        // Timeout overridden
        $this->assertSame(60, $apps['imp']['params']['timeout']);
        // SSL preserved from vendor
        $this->assertFalse($apps['imp']['params']['ssl']);
        // Name preserved from vendor
        $this->assertSame('IMP', $apps['imp']['name']);
    }

    public function testNoClosureHandling(): void
    {
        // Registry doesn't use closures, just simple arrays
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['horde'] = [
                    'name' => 'Horde',
                    'provides' => 'horde',
                ];
                PHP
        );

        $state = $this->loader->load();
        $apps = $state->toArray();

        // Just verify it loads correctly
        $this->assertIsArray($apps['horde']);
        $this->assertIsString($apps['horde']['name']);
    }

    // ===================================================================
    // E. Load Layer Introspection (1 test)
    // ===================================================================

    public function testLoadLayerForIntrospection(): void
    {
        // Vendor file
        $vendorFile = $this->vendorDir . '/config/registry.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $this->applications['horde'] = ['name' => 'Horde'];
                PHP
        );

        // Local file
        $localFile = $this->configDir . '/horde/registry.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $this->applications['imp'] = ['name' => 'IMP'];
                PHP
        );

        // Load specific layers
        $vendorLayer = $this->loader->loadLayer('vendor');
        $localLayer = $this->loader->loadLayer('local');

        // Vendor layer has only horde
        $this->assertArrayHasKey('horde', $vendorLayer);
        $this->assertArrayNotHasKey('imp', $vendorLayer);

        // Local layer has only imp
        $this->assertArrayHasKey('imp', $localLayer);
        $this->assertArrayNotHasKey('horde', $localLayer);
    }

    // ===================================================================
    // F. Compiled-registry short-circuit
    // ===================================================================

    /**
     * Write a compiled-registry PHP file at the given path.
     *
     * Mirrors the format RegistryConfigWriter emits: a plain
     * `<?php return [...];` file the loader `require`s.
     */
    private function writeCompiledFile(string $path, array $compiled): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o755, true);
        }
        file_put_contents(
            $path,
            "<?php\n\nreturn " . var_export($compiled, true) . ";\n"
        );
    }

    public function testCompiledFileShortCircuitsLayerScan(): void
    {
        // Write a *conflicting* base file that would produce a
        // different result if the layers were scanned. A load that
        // honours the compiled file must ignore it.
        file_put_contents(
            $this->configDir . '/horde/registry.php',
            <<<'PHP'
                <?php
                $this->applications['horde'] = ['name' => 'From base file'];
                PHP
        );

        $compiledPath = $this->tempDir . '/cache/compiled/registry.php';
        $this->writeCompiledFile($compiledPath, [
            'default' => [
                'horde' => ['name' => 'From compiled file'],
            ],
        ]);

        $loader = new RegistryConfigLoader(
            $this->configDir,
            $this->vendorDir,
            compiledRegistryPath: $compiledPath,
        );

        $state = $loader->load();
        $apps = $state->toArray();

        // Compiled wins: the base file was not scanned.
        $this->assertSame('From compiled file', $apps['horde']['name']);
    }

    public function testCompiledFileMergesVhostDeltaOntoDefault(): void
    {
        $compiledPath = $this->tempDir . '/cache/compiled/registry.php';
        $this->writeCompiledFile($compiledPath, [
            'default' => [
                'imp' => [
                    'name' => 'IMP',
                    'webroot' => '/imp',
                    'fileroot' => '/var/www/imp',
                    'jsuri' => '/imp/js',
                    'themesuri' => '/imp/themes',
                    'status' => 'active',
                ],
            ],
            'example.com' => [
                'imp' => ['webroot' => '/mail'],
            ],
        ]);

        $loader = new RegistryConfigLoader(
            $this->configDir,
            $this->vendorDir,
            'example.com',
            compiledRegistryPath: $compiledPath,
        );

        $apps = $loader->load()->toArray();

        // Vhost delta overrides webroot. Default preserves the rest.
        $this->assertSame('/mail', $apps['imp']['webroot']);
        $this->assertSame('IMP', $apps['imp']['name']);
        $this->assertSame('active', $apps['imp']['status']);
    }

    public function testCompiledFileFallsBackToDefaultForUnknownVhost(): void
    {
        $compiledPath = $this->tempDir . '/cache/compiled/registry.php';
        $this->writeCompiledFile($compiledPath, [
            'default' => [
                'imp' => ['webroot' => '/imp'],
            ],
            'known.example.com' => [
                'imp' => ['webroot' => '/mail-known'],
            ],
        ]);

        // Loader configured for a vhost that has no slot in the
        // compiled artifact. Should silently fall back to default.
        $loader = new RegistryConfigLoader(
            $this->configDir,
            $this->vendorDir,
            'unknown.example.com',
            compiledRegistryPath: $compiledPath,
        );

        $apps = $loader->load()->toArray();
        $this->assertSame('/imp', $apps['imp']['webroot']);
    }

    public function testCompiledFileMissingFallsBackToLayerScan(): void
    {
        // Layer files present, compiled-registry path pointed at a
        // path that doesn't exist. Loader must not raise and must
        // return the same result the layer scan would produce.
        file_put_contents(
            $this->configDir . '/horde/registry.php',
            <<<'PHP'
                <?php
                $this->applications['horde'] = ['name' => 'Layer scan'];
                PHP
        );

        $loader = new RegistryConfigLoader(
            $this->configDir,
            $this->vendorDir,
            compiledRegistryPath: $this->tempDir . '/does-not-exist.php',
        );

        $apps = $loader->load()->toArray();
        $this->assertSame('Layer scan', $apps['horde']['name']);
    }

    public function testMalformedCompiledFileFallsBackToLayerScan(): void
    {
        // A corrupt or wrong-shape compiled file (not an array, or
        // missing 'default') should not brick the loader. The layer
        // scan is the safe fallback.
        file_put_contents(
            $this->configDir . '/horde/registry.php',
            <<<'PHP'
                <?php
                $this->applications['horde'] = ['name' => 'Layer scan'];
                PHP
        );

        $compiledPath = $this->tempDir . '/cache/compiled/registry.php';
        if (!is_dir(dirname($compiledPath))) {
            mkdir(dirname($compiledPath), 0o755, true);
        }
        file_put_contents(
            $compiledPath,
            "<?php\n\nreturn 'not an array';\n"
        );

        $loader = new RegistryConfigLoader(
            $this->configDir,
            $this->vendorDir,
            compiledRegistryPath: $compiledPath,
        );

        $apps = $loader->load()->toArray();
        $this->assertSame('Layer scan', $apps['horde']['name']);
    }

    public function testDefaultCompiledPathIsSiblingCacheDir(): void
    {
        // When no explicit compiledRegistryPath is passed, the loader
        // derives dirname($configBase) . '/cache/compiled/registry.php'.
        // Simulate a bundle-shaped var/{config,cache}/ layout and
        // check the loader picks up the file at the expected place.
        $bundleVar = $this->tempDir . '/var';
        mkdir($bundleVar . '/config/horde', 0o755, true);
        mkdir($bundleVar . '/cache/compiled', 0o755, true);

        $this->writeCompiledFile(
            $bundleVar . '/cache/compiled/registry.php',
            ['default' => ['horde' => ['name' => 'From default path']]]
        );

        // Layers under var/config/ would produce a different result
        // Layers under var/config/ would produce a different result
        // if scanned. Do not seed them. Silence is the assertion
        // that layer scanning was skipped.
        $loader = new RegistryConfigLoader(
            $bundleVar . '/config',
            $this->vendorDir,
        );

        $apps = $loader->load()->toArray();
        $this->assertSame('From default path', $apps['horde']['name']);
    }
}
