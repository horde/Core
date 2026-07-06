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

use Horde\Core\Config\RegistryConfigCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for RegistryConfigCompiler.
 *
 * The compiler folds vendor defaults, deployment base, snippets, and
 * local overrides into a single 'default' array, and captures each
 * vhost's raw file contents as a separate delta slot.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(RegistryConfigCompiler::class)]
class RegistryConfigCompilerTest extends TestCase
{
    private string $tempDir;
    private string $vendorDir;
    private string $configDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/horde-registry-compiler-test-' . uniqid();
        $this->vendorDir = $this->tempDir . '/vendor/horde/horde';
        $this->configDir = $this->tempDir . '/config';

        mkdir($this->vendorDir . '/config', 0o755, true);
        mkdir($this->configDir . '/horde', 0o755, true);
    }

    protected function tearDown(): void
    {
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

    /** Convenience: write a registry file with $this->applications syntax. */
    private function writeRegistryFile(string $path, array $applications): void
    {
        $lines = ["<?php"];
        foreach ($applications as $name => $spec) {
            $lines[] = '$this->applications[' . var_export($name, true) . '] = '
                . var_export($spec, true) . ';';
        }
        file_put_contents($path, implode("\n", $lines) . "\n");
    }

    // ===================================================================
    // Default layer merge
    // ===================================================================

    public function testCompileWithOnlyVendorProducesDefault(): void
    {
        $this->writeRegistryFile(
            $this->vendorDir . '/config/registry.php',
            ['horde' => ['name' => 'Horde', 'status' => 'active']]
        );

        $compiler = new RegistryConfigCompiler($this->configDir, $this->vendorDir);
        $result = $compiler->compile();

        $this->assertArrayHasKey('default', $result);
        $this->assertSame('Horde', $result['default']['horde']['name']);
        $this->assertSame('active', $result['default']['horde']['status']);
    }

    public function testCompileMergesAllFourDefaultLayers(): void
    {
        // Layer 1: vendor - sets everything on imp
        $this->writeRegistryFile(
            $this->vendorDir . '/config/registry.php',
            ['imp' => [
                'name' => 'IMP',
                'status' => 'inactive',
                'params' => ['timeout' => 30, 'ssl' => false],
            ]]
        );

        // Layer 2: deployment base - overrides status
        $this->writeRegistryFile(
            $this->configDir . '/horde/registry.php',
            ['imp' => ['status' => 'active']]
        );

        // Layer 3: snippet - overrides timeout
        mkdir($this->configDir . '/horde/registry.d', 0o755, true);
        $this->writeRegistryFile(
            $this->configDir . '/horde/registry.d/01-timeouts.php',
            ['imp' => ['params' => ['timeout' => 60]]]
        );

        // Layer 4: local - overrides ssl
        $this->writeRegistryFile(
            $this->configDir . '/horde/registry.local.php',
            ['imp' => ['params' => ['ssl' => true]]]
        );

        $compiler = new RegistryConfigCompiler($this->configDir, $this->vendorDir);
        $result = $compiler->compile();

        // Layer precedence: local > snippet > base > vendor
        $imp = $result['default']['imp'];
        $this->assertSame('IMP', $imp['name'], 'name preserved from vendor');
        $this->assertSame('active', $imp['status'], 'status overridden by base');
        $this->assertSame(60, $imp['params']['timeout'], 'timeout overridden by snippet');
        $this->assertTrue($imp['params']['ssl'], 'ssl overridden by local');
    }

    public function testCompileLoadsSnippetsInSortedOrder(): void
    {
        // 01 sets the value; 02 overrides. If load order were reversed
        // (or non-deterministic across filesystems), the test would flap.
        $this->writeRegistryFile(
            $this->vendorDir . '/config/registry.php',
            ['imp' => ['name' => 'IMP']]
        );

        mkdir($this->configDir . '/horde/registry.d', 0o755, true);
        $this->writeRegistryFile(
            $this->configDir . '/horde/registry.d/01-set.php',
            ['imp' => ['status' => 'first']]
        );
        $this->writeRegistryFile(
            $this->configDir . '/horde/registry.d/02-override.php',
            ['imp' => ['status' => 'second']]
        );

        $compiler = new RegistryConfigCompiler($this->configDir, $this->vendorDir);
        $result = $compiler->compile();

        $this->assertSame('second', $result['default']['imp']['status']);
    }

    public function testCompileWithNoFilesProducesEmptyDefault(): void
    {
        $compiler = new RegistryConfigCompiler($this->configDir, $this->vendorDir);
        $result = $compiler->compile();

        $this->assertSame(['default' => []], $result);
    }

    // ===================================================================
    // Vhost discovery and slots
    // ===================================================================

    public function testCompileAutoDiscoversVhostFiles(): void
    {
        $this->writeRegistryFile(
            $this->vendorDir . '/config/registry.php',
            ['imp' => ['name' => 'IMP', 'webroot' => '/imp']]
        );

        $this->writeRegistryFile(
            $this->configDir . '/horde/registry-foo.example.com.php',
            ['imp' => ['webroot' => '/mail-foo']]
        );

        $this->writeRegistryFile(
            $this->configDir . '/horde/registry-bar.example.com.php',
            ['imp' => ['webroot' => '/mail-bar']]
        );

        $compiler = new RegistryConfigCompiler($this->configDir, $this->vendorDir);
        $result = $compiler->compile();

        $this->assertArrayHasKey('foo.example.com', $result);
        $this->assertArrayHasKey('bar.example.com', $result);
        $this->assertSame('/mail-foo', $result['foo.example.com']['imp']['webroot']);
        $this->assertSame('/mail-bar', $result['bar.example.com']['imp']['webroot']);
    }

    public function testCompileStoresRawVhostDeltaNotMergedRegistry(): void
    {
        // Vendor establishes lots of baseline properties.
        $this->writeRegistryFile(
            $this->vendorDir . '/config/registry.php',
            ['imp' => [
                'name' => 'IMP',
                'status' => 'active',
                'webroot' => '/imp',
                'fileroot' => '/var/www/imp',
            ]]
        );

        // Vhost changes only webroot.
        $this->writeRegistryFile(
            $this->configDir . '/horde/registry-example.com.php',
            ['imp' => ['webroot' => '/mail']]
        );

        $compiler = new RegistryConfigCompiler($this->configDir, $this->vendorDir);
        $result = $compiler->compile();

        // The vhost slot holds only the delta, not the full merged shape.
        // RegistryConfigLoader recombines default + vhost at read time.
        $this->assertSame(
            ['imp' => ['webroot' => '/mail']],
            $result['example.com']
        );
    }

    public function testCompileWithExplicitVhostList(): void
    {
        $this->writeRegistryFile(
            $this->vendorDir . '/config/registry.php',
            ['imp' => ['name' => 'IMP']]
        );

        // Two vhost files on disk.
        $this->writeRegistryFile(
            $this->configDir . '/horde/registry-included.example.com.php',
            ['imp' => ['webroot' => '/mail-included']]
        );
        $this->writeRegistryFile(
            $this->configDir . '/horde/registry-skipped.example.com.php',
            ['imp' => ['webroot' => '/mail-skipped']]
        );

        // Caller asks for only one of them plus one that doesn't exist.
        $compiler = new RegistryConfigCompiler(
            $this->configDir,
            $this->vendorDir,
            vhosts: ['included.example.com', 'never-existed.example.com']
        );
        $result = $compiler->compile();

        $this->assertArrayHasKey('included.example.com', $result);
        $this->assertArrayNotHasKey(
            'skipped.example.com',
            $result,
            'explicit list should suppress auto-discovery'
        );
        $this->assertArrayNotHasKey(
            'never-existed.example.com',
            $result,
            'listed but missing vhost file should be silently skipped'
        );
    }

    public function testCompileWithEmptyExplicitListYieldsDefaultOnly(): void
    {
        $this->writeRegistryFile(
            $this->vendorDir . '/config/registry.php',
            ['horde' => ['name' => 'Horde']]
        );
        // Vhost file present, but explicit empty list overrides discovery.
        $this->writeRegistryFile(
            $this->configDir . '/horde/registry-ignored.example.com.php',
            ['horde' => ['webroot' => '/nope']]
        );

        $compiler = new RegistryConfigCompiler(
            $this->configDir,
            $this->vendorDir,
            vhosts: []
        );
        $result = $compiler->compile();

        $this->assertSame(['default'], array_keys($result));
    }

    public function testCompileHandlesVhostWithHyphensInHostname(): void
    {
        // registry-<vhost>.php filename parsing must survive vhost names
        // that themselves contain hyphens (registry-my-app.example.com.php).
        $this->writeRegistryFile(
            $this->vendorDir . '/config/registry.php',
            ['horde' => ['name' => 'Horde']]
        );
        $this->writeRegistryFile(
            $this->configDir . '/horde/registry-my-app.example.com.php',
            ['horde' => ['webroot' => '/tenant']]
        );

        $compiler = new RegistryConfigCompiler($this->configDir, $this->vendorDir);
        $result = $compiler->compile();

        $this->assertArrayHasKey('my-app.example.com', $result);
    }
}
