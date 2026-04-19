<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Config;

use Horde\Core\Config\BackendConfigLoader;
use Horde\Core\Config\BackendState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for BackendConfigLoader
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(BackendConfigLoader::class)]
class BackendConfigLoaderTest extends TestCase
{
    private string $tempDir;
    private string $vendorDir;
    private string $configDir;
    private BackendConfigLoader $loader;

    protected function setUp(): void
    {
        // Create temp directory structure
        $this->tempDir = sys_get_temp_dir() . '/horde-backend-test-' . uniqid();
        $this->vendorDir = $this->tempDir . '/vendor/horde';
        $this->configDir = $this->tempDir . '/config';

        mkdir($this->vendorDir . '/passwd/config', 0o755, true);
        mkdir($this->configDir . '/passwd', 0o755, true);

        $this->loader = new BackendConfigLoader($this->configDir, $this->vendorDir);
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

    public function testLoadVendorDefaults(): void
    {
        // Create vendor defaults
        $vendorFile = $this->vendorDir . '/passwd/config/backends.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $backends['hordesql'] = [
                    'disabled' => true,
                    'name' => 'Horde SQL',
                    'driver' => 'Sql',
                ];
                $backends['ldap'] = [
                    'disabled' => true,
                    'name' => 'LDAP',
                    'driver' => 'Ldap',
                ];
                PHP
        );

        $state = $this->loader->load('passwd');

        $this->assertInstanceOf(BackendState::class, $state);
        $this->assertTrue($state->hasBackend('hordesql'));
        $this->assertTrue($state->hasBackend('ldap'));
    }

    public function testLocalOverride(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/passwd/config/backends.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $backends['hordesql'] = [
                    'disabled' => true,
                    'name' => 'Horde SQL',
                    'driver' => 'Sql',
                    'params' => ['table' => 'default_table'],
                ];
                PHP
        );

        // Local override
        $localFile = $this->configDir . '/passwd/backends.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $backends['hordesql'] = [
                    'disabled' => false,
                    'params' => ['table' => 'custom_table'],
                ];
                PHP
        );

        $state = $this->loader->load('passwd');
        $backend = $state->getBackend('hordesql');

        // Verify override
        $this->assertFalse($backend['disabled']);
        $this->assertEquals('custom_table', $backend['params']['table']);
        // Verify vendor defaults still present
        $this->assertEquals('Horde SQL', $backend['name']);
        $this->assertEquals('Sql', $backend['driver']);
    }

    public function testSnippets(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/passwd/config/backends.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $backends['hordesql'] = [
                    'disabled' => true,
                    'name' => 'Horde SQL',
                ];
                PHP
        );

        // Create snippets directory
        mkdir($this->configDir . '/passwd/backends.d', 0o755, true);

        // Snippet 1
        $snippet1 = $this->configDir . '/passwd/backends.d/01-ldap.php';
        file_put_contents(
            $snippet1,
            <<<'PHP'
                <?php
                $backends['ldap'] = [
                    'disabled' => false,
                    'name' => 'LDAP Server',
                ];
                PHP
        );

        // Snippet 2
        $snippet2 = $this->configDir . '/passwd/backends.d/02-poppassd.php';
        file_put_contents(
            $snippet2,
            <<<'PHP'
                <?php
                $backends['poppassd'] = [
                    'disabled' => false,
                    'name' => 'Poppassd',
                ];
                PHP
        );

        $state = $this->loader->load('passwd');

        $this->assertTrue($state->hasBackend('hordesql'));
        $this->assertTrue($state->hasBackend('ldap'));
        $this->assertTrue($state->hasBackend('poppassd'));
    }

    public function testCaching(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/passwd/config/backends.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $backends['hordesql'] = [
                    'name' => 'Horde SQL',
                ];
                PHP
        );

        // First load
        $state1 = $this->loader->load('passwd');

        // Second load (should return cached)
        $state2 = $this->loader->load('passwd');

        $this->assertSame($state1, $state2);
    }

    public function testClearCache(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/passwd/config/backends.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $backends['hordesql'] = [
                    'name' => 'Horde SQL',
                ];
                PHP
        );

        // First load
        $state1 = $this->loader->load('passwd');

        // Clear cache
        $this->loader->clearCache();

        // Second load (should not be cached)
        $state2 = $this->loader->load('passwd');

        $this->assertNotSame($state1, $state2);
    }

    public function testLoadLayerVendor(): void
    {
        $vendorFile = $this->vendorDir . '/passwd/config/backends.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $backends['hordesql'] = [
                    'name' => 'Vendor SQL',
                ];
                PHP
        );

        $localFile = $this->configDir . '/passwd/backends.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $backends['hordesql'] = [
                    'name' => 'Local SQL',
                ];
                PHP
        );

        $vendorLayer = $this->loader->loadLayer('passwd', 'vendor');

        $this->assertEquals('Vendor SQL', $vendorLayer['hordesql']['name']);
    }

    public function testLoadLayerLocal(): void
    {
        $vendorFile = $this->vendorDir . '/passwd/config/backends.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $backends['hordesql'] = [
                    'name' => 'Vendor SQL',
                ];
                PHP
        );

        $localFile = $this->configDir . '/passwd/backends.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $backends['hordesql'] = [
                    'name' => 'Local SQL',
                ];
                PHP
        );

        $localLayer = $this->loader->loadLayer('passwd', 'local');

        $this->assertEquals('Local SQL', $localLayer['hordesql']['name']);
    }

    public function testDisabledFiltering(): void
    {
        $vendorFile = $this->vendorDir . '/passwd/config/backends.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $backends['enabled_backend'] = [
                    'disabled' => false,
                    'name' => 'Enabled',
                ];
                $backends['disabled_backend'] = [
                    'disabled' => true,
                    'name' => 'Disabled',
                ];
                $backends['no_flag_backend'] = [
                    'name' => 'No Flag',
                ];
                PHP
        );

        $state = $this->loader->load('passwd');

        // By default, disabled backends excluded
        $enabled = $state->listBackends(false);
        $this->assertArrayHasKey('enabled_backend', $enabled);
        $this->assertArrayNotHasKey('disabled_backend', $enabled);
        $this->assertArrayHasKey('no_flag_backend', $enabled);

        // With flag, disabled included
        $all = $state->listBackends(true);
        $this->assertArrayHasKey('enabled_backend', $all);
        $this->assertArrayHasKey('disabled_backend', $all);
        $this->assertArrayHasKey('no_flag_backend', $all);
    }

    public function testMultipleApps(): void
    {
        // Setup passwd app (already exists from setUp)
        $passwdFile = $this->vendorDir . '/passwd/config/backends.php';
        file_put_contents(
            $passwdFile,
            <<<'PHP'
                <?php
                $backends['passwd_backend'] = [
                    'name' => 'Passwd Backend',
                ];
                PHP
        );

        // Setup imp app
        mkdir($this->vendorDir . '/imp/config', 0o755, true);
        mkdir($this->configDir . '/imp', 0o755, true);
        $impFile = $this->vendorDir . '/imp/config/backends.php';
        file_put_contents(
            $impFile,
            <<<'PHP'
                <?php
                $backends['imp_backend'] = [
                    'name' => 'IMP Backend',
                ];
                PHP
        );

        $passwdState = $this->loader->load('passwd');
        $impState = $this->loader->load('imp');

        $this->assertTrue($passwdState->hasBackend('passwd_backend'));
        $this->assertFalse($passwdState->hasBackend('imp_backend'));

        $this->assertTrue($impState->hasBackend('imp_backend'));
        $this->assertFalse($impState->hasBackend('passwd_backend'));
    }
}
