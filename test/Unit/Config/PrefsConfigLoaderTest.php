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

use Closure;
use Horde\Core\Config\PrefsConfigLoader;
use Horde\Core\Config\PrefsState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PrefsConfigLoader.
 *
 * PrefsConfigLoader loads preference definitions from prefs.php files
 * with 5-layer hierarchical configuration system similar to BackendConfigLoader
 * but for $_prefs and $prefGroups variables instead of $backends.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(PrefsConfigLoader::class)]
class PrefsConfigLoaderTest extends TestCase
{
    private string $tempDir;
    private string $vendorDir;
    private string $configDir;
    private PrefsConfigLoader $loader;

    protected function setUp(): void
    {
        // Create temp directory structure
        $this->tempDir = sys_get_temp_dir() . '/horde-prefs-test-' . uniqid();
        $this->vendorDir = $this->tempDir . '/vendor/horde';
        $this->configDir = $this->tempDir . '/config';

        mkdir($this->vendorDir . '/horde/config', 0o755, true);
        mkdir($this->configDir . '/horde', 0o755, true);

        $this->loader = new PrefsConfigLoader($this->configDir, $this->vendorDir);
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
        // Create vendor defaults
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = [
                    'value' => 'en_US',
                    'type' => 'select',
                    'desc' => 'Select your preferred language',
                ];
                $_prefs['theme'] = [
                    'value' => 'default',
                    'type' => 'select',
                ];
                $prefGroups['display'] = [
                    'label' => 'Display Preferences',
                    'desc' => 'Customize the display',
                    'members' => ['language', 'theme'],
                ];
                PHP
        );

        $state = $this->loader->load('horde');

        $this->assertInstanceOf(PrefsState::class, $state);
        $this->assertTrue($state->hasPref('language'));
        $this->assertTrue($state->hasPref('theme'));
        $this->assertSame('en_US', $state->getPref('language')['value']);
    }

    public function testLoadDeploymentBaseConfig(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = [
                    'value' => 'en_US',
                    'type' => 'select',
                ];
                PHP
        );

        // Deployment base config overrides vendor
        $baseFile = $this->configDir . '/horde/prefs.php';
        file_put_contents(
            $baseFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = [
                    'value' => 'de_DE',
                ];
                PHP
        );

        $state = $this->loader->load('horde');
        $language = $state->getPref('language');

        // Base config overrides vendor
        $this->assertSame('de_DE', $language['value']);
        // But type is preserved from vendor
        $this->assertSame('select', $language['type']);
    }

    public function testLoadConfigSnippets(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = [
                    'value' => 'en_US',
                    'type' => 'select',
                ];
                PHP
        );

        // Create snippets directory
        mkdir($this->configDir . '/horde/prefs.d', 0o755, true);

        // Snippet 1 (loads alphabetically)
        $snippet1 = $this->configDir . '/horde/prefs.d/01-regional.php';
        file_put_contents(
            $snippet1,
            <<<'PHP'
                <?php
                $_prefs['timezone'] = [
                    'value' => 'UTC',
                    'type' => 'select',
                ];
                PHP
        );

        // Snippet 2 (loads after snippet1)
        $snippet2 = $this->configDir . '/horde/prefs.d/02-theme.php';
        file_put_contents(
            $snippet2,
            <<<'PHP'
                <?php
                $_prefs['theme'] = [
                    'value' => 'silver',
                    'type' => 'select',
                ];
                PHP
        );

        $state = $this->loader->load('horde');

        // All prefs loaded
        $this->assertTrue($state->hasPref('language'));
        $this->assertTrue($state->hasPref('timezone'));
        $this->assertTrue($state->hasPref('theme'));
        $this->assertSame('silver', $state->getPref('theme')['value']);
    }

    public function testLoadLocalOverrides(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = [
                    'value' => 'en_US',
                    'type' => 'select',
                    'desc' => 'Select your language',
                ];
                PHP
        );

        // Local override
        $localFile = $this->configDir . '/horde/prefs.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = [
                    'value' => 'fr_FR',
                ];
                PHP
        );

        $state = $this->loader->load('horde');
        $language = $state->getPref('language');

        // Local overrides value
        $this->assertSame('fr_FR', $language['value']);
        // But preserves type and desc from vendor
        $this->assertSame('select', $language['type']);
        $this->assertSame('Select your language', $language['desc']);
    }

    public function testLoadVhostOverrides(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = [
                    'value' => 'en_US',
                ];
                PHP
        );

        // VHost override for example.com
        $vhostFile = $this->configDir . '/horde/prefs-example.com.php';
        file_put_contents(
            $vhostFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = [
                    'value' => 'es_ES',
                ];
                PHP
        );

        // Create loader with hostname
        $vhostLoader = new PrefsConfigLoader(
            $this->configDir,
            $this->vendorDir,
            'example.com'
        );

        $state = $vhostLoader->load('horde');
        $language = $state->getPref('language');

        // VHost overrides value
        $this->assertSame('es_ES', $language['value']);
    }

    // ===================================================================
    // B. Merge Behavior (3 tests)
    // ===================================================================

    public function testFieldMerge(): void
    {
        // Vendor defaults with nested structure
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = [
                    'value' => 'en_US',
                    'type' => 'select',
                    'desc' => 'Choose language',
                ];
                PHP
        );

        // Local override changes only value
        $localFile = $this->configDir . '/horde/prefs.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = [
                    'value' => 'de_DE',
                ];
                PHP
        );

        $state = $this->loader->load('horde');
        $language = $state->getPref('language');

        // Value overridden
        $this->assertSame('de_DE', $language['value']);
        // Other fields preserved from vendor
        $this->assertSame('select', $language['type']);
        $this->assertSame('Choose language', $language['desc']);
    }

    public function testClosurePreservation(): void
    {
        // Vendor defaults with closure
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['computed'] = [
                    'value' => function() { return 'vendor'; },
                    'type' => 'implicit',
                ];
                PHP
        );

        // Local tries to override the closure
        $localFile = $this->configDir . '/horde/prefs.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $_prefs['computed'] = [
                    'value' => function() { return 'local'; },
                    'type' => 'override',
                ];
                PHP
        );

        $state = $this->loader->load('horde');
        $computed = $state->getPref('computed');

        // First closure wins (from vendor)
        $this->assertInstanceOf(Closure::class, $computed['value']);
        $this->assertSame('vendor', ($computed['value'])());
        // But type is overridden
        $this->assertSame('override', $computed['type']);
    }

    public function testPrefGroupsMerge(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $prefGroups['display'] = [
                    'label' => 'Display Preferences',
                    'desc' => 'Customize the display',
                    'members' => ['language', 'theme'],
                ];
                PHP
        );

        // Local override adds members
        $localFile = $this->configDir . '/horde/prefs.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $prefGroups['display'] = [
                    'members' => ['language', 'theme', 'timezone'],
                ];
                PHP
        );

        $state = $this->loader->load('horde');
        $groups = $state->toArray()['prefGroups'];

        // Members overridden
        $this->assertSame(['language', 'theme', 'timezone'], $groups['display']['members']);
        // Label and desc preserved
        $this->assertSame('Display Preferences', $groups['display']['label']);
        $this->assertSame('Customize the display', $groups['display']['desc']);
    }

    // ===================================================================
    // C. Caching (2 tests)
    // ===================================================================

    public function testSameAppReturnsCachedInstance(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = ['value' => 'en_US'];
                PHP
        );

        $state1 = $this->loader->load('horde');
        $state2 = $this->loader->load('horde');

        // Same instance returned (cached)
        $this->assertSame($state1, $state2);
    }

    public function testClearCacheInvalidatesCache(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = ['value' => 'en_US'];
                PHP
        );

        $state1 = $this->loader->load('horde');
        $this->loader->clearCache();
        $state2 = $this->loader->load('horde');

        // Different instances after cache clear
        $this->assertNotSame($state1, $state2);
        // But same data
        $this->assertSame($state1->getPref('language'), $state2->getPref('language'));
    }

    // ===================================================================
    // D. File Handling (2 tests)
    // ===================================================================

    public function testMissingFilesHandledGracefully(): void
    {
        // No files exist
        $state = $this->loader->load('nonexistent');

        $this->assertInstanceOf(PrefsState::class, $state);
        // Empty state
        $this->assertEmpty($state->toArray()['_prefs']);
    }

    public function testLoadLayerForIntrospection(): void
    {
        // Create vendor file
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = ['value' => 'en_US'];
                PHP
        );

        // Create local file
        $localFile = $this->configDir . '/horde/prefs.local.php';
        file_put_contents(
            $localFile,
            <<<'PHP'
                <?php
                $_prefs['theme'] = ['value' => 'silver'];
                PHP
        );

        // Load specific layers
        $vendorLayer = $this->loader->loadLayer('horde', 'vendor');
        $localLayer = $this->loader->loadLayer('horde', 'local');

        // Vendor layer has only language
        $this->assertArrayHasKey('language', $vendorLayer['_prefs']);
        $this->assertArrayNotHasKey('theme', $vendorLayer['_prefs']);

        // Local layer has only theme
        $this->assertArrayHasKey('theme', $localLayer['_prefs']);
        $this->assertArrayNotHasKey('language', $localLayer['_prefs']);
    }

    // ===================================================================
    // E. State Return (3 tests)
    // ===================================================================

    public function testReturnsPrefsStateInstance(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = ['value' => 'en_US'];
                PHP
        );

        $state = $this->loader->load('horde');

        $this->assertInstanceOf(PrefsState::class, $state);
    }

    public function testStateIsImmutable(): void
    {
        // Vendor defaults
        $vendorFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $vendorFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = ['value' => 'en_US'];
                PHP
        );

        $state = $this->loader->load('horde');
        $array = $state->toArray();

        // Modify array
        $array['_prefs']['language']['value'] = 'changed';

        // State unchanged
        $this->assertSame('en_US', $state->getPref('language')['value']);
    }

    public function testMultipleAppsDontInterfere(): void
    {
        // Create horde prefs
        $hordeFile = $this->vendorDir . '/horde/config/prefs.php';
        file_put_contents(
            $hordeFile,
            <<<'PHP'
                <?php
                $_prefs['language'] = ['value' => 'en_US'];
                PHP
        );

        // Create imp prefs
        @mkdir($this->vendorDir . '/imp/config', 0o755, true);
        @mkdir($this->configDir . '/imp', 0o755, true);
        $impFile = $this->vendorDir . '/imp/config/prefs.php';
        file_put_contents(
            $impFile,
            <<<'PHP'
                <?php
                $_prefs['signature'] = ['value' => 'My signature'];
                PHP
        );

        $hordeState = $this->loader->load('horde');
        $impState = $this->loader->load('imp');

        // horde has language, not signature
        $this->assertTrue($hordeState->hasPref('language'));
        $this->assertFalse($hordeState->hasPref('signature'));

        // imp has signature, not language
        $this->assertTrue($impState->hasPref('signature'));
        $this->assertFalse($impState->hasPref('language'));
    }
}
