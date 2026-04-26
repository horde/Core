<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\ResponsiveAssets;
use Horde\Core\Assets\ResponsiveAssetsFilesystem;
use Horde\Core\Config\RegistryState;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde_Prefs;

/**
 * Tests for ResponsiveAssets helper class
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(ResponsiveAssets::class)]
class ResponsiveAssetsTest extends TestCase
{
    private ResponsiveAssetsFilesystem $filesystemStub;

    protected function setUp(): void
    {
        $this->filesystemStub = $this->createStub(ResponsiveAssetsFilesystem::class);
    }

    private function buildState(array $apps): RegistryState
    {
        return new RegistryState($apps);
    }

    public function testGetCssUrlsHordeOnly(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'webroot' => '/horde',
                'fileroot' => '/horde',
                'themesfs' => '/horde/themes',
                'themesuri' => '/themes/horde',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/horde/themes/default/responsive.css';
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getCssUrls('horde', 'default');

        $this->assertIsArray($urls);
        $this->assertCount(1, $urls);
        $this->assertEquals('/themes/horde/default/responsive.css', $urls[0]);
    }

    public function testGetCssUrlsWithAppCascade(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'themesfs' => '/horde/themes',
                'themesuri' => '/themes/horde',
            ],
            'kronolith' => [
                'status' => 'active',
                'themesfs' => '/kronolith/themes',
                'themesuri' => '/themes/kronolith',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return str_contains($path, 'responsive.css');
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getCssUrls('kronolith', 'default');

        $this->assertCount(2, $urls);
        $this->assertEquals('/themes/horde/default/responsive.css', $urls[0]);
        $this->assertEquals('/themes/kronolith/default/responsive.css', $urls[1]);
    }

    public function testGetCssUrlsFileNotFound(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'themesfs' => '/horde/themes',
                'themesuri' => '/themes/horde',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturn(false);

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getCssUrls('horde', 'default');

        $this->assertEmpty($urls);
    }

    public function testGetCssUrlsOnlyAppFileExists(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'themesfs' => '/horde/themes',
                'themesuri' => '/themes/horde',
            ],
            'kronolith' => [
                'status' => 'active',
                'themesfs' => '/kronolith/themes',
                'themesuri' => '/themes/kronolith',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return str_contains($path, '/kronolith/');
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getCssUrls('kronolith', 'default');

        $this->assertCount(1, $urls);
        $this->assertEquals('/themes/kronolith/default/responsive.css', $urls[0]);
    }

    public function testGetJsUrlsHordeOnly(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'jsfs' => '/horde/js',
                'jsuri' => '/js/horde',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/horde/js/login-form.js';
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getJsUrls('horde', ['login-form.js']);

        $this->assertIsArray($urls);
        $this->assertCount(1, $urls);
        $this->assertEquals('/js/horde/login-form.js', $urls[0]);
    }

    public function testGetJsUrlsWithAppCascade(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'jsfs' => '/horde/js',
                'jsuri' => '/js/horde',
            ],
            'kronolith' => [
                'status' => 'active',
                'jsfs' => '/kronolith/js',
                'jsuri' => '/js/kronolith',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturn(true);

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getJsUrls('kronolith', ['calendar.js']);

        $this->assertCount(2, $urls);
        $this->assertEquals('/js/horde/calendar.js', $urls[0]);
        $this->assertEquals('/js/kronolith/calendar.js', $urls[1]);
    }

    public function testGetJsUrlsMultipleFiles(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'jsfs' => '/horde/js',
                'jsuri' => '/js/horde',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturn(true);

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getJsUrls('horde', ['file1.js', 'file2.js', 'file3.js']);

        $this->assertCount(3, $urls);
        $this->assertEquals('/js/horde/file1.js', $urls[0]);
        $this->assertEquals('/js/horde/file2.js', $urls[1]);
        $this->assertEquals('/js/horde/file3.js', $urls[2]);
    }

    public function testGetJsUrlsEmptyArray(): void
    {
        $state = $this->buildState([
            'horde' => ['status' => 'active'],
        ]);

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getJsUrls('horde', []);

        $this->assertEmpty($urls);
    }

    public function testGetTheme(): void
    {
        $GLOBALS['prefs'] = null;
        $state = $this->buildState(['horde' => ['status' => 'active']]);

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $theme = $assets->getTheme();

        $this->assertEquals('default', $theme);
    }

    public function testGetThemeWithPreference(): void
    {
        $prefsStub = $this->createStub(Horde_Prefs::class);
        $prefsStub->method('getValue')
            ->willReturnCallback(function ($key) {
                return $key === 'theme' ? 'dark' : null;
            });

        $GLOBALS['prefs'] = $prefsStub;
        $state = $this->buildState(['horde' => ['status' => 'active']]);

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $theme = $assets->getTheme();

        $this->assertEquals('dark', $theme);

        unset($GLOBALS['prefs']);
    }

    public function testCssUrlsWithMissingRegistryParam(): void
    {
        $state = $this->buildState([
            'horde' => ['status' => 'active'],
        ]);

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getCssUrls('horde', 'default');

        $this->assertEmpty($urls);
    }

    public function testJsUrlsWithMissingRegistryParam(): void
    {
        $state = $this->buildState([
            'horde' => ['status' => 'active'],
        ]);

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getJsUrls('horde', ['test.js']);

        $this->assertEmpty($urls);
    }

    public function testGetGraphicUrlAppThemeFirst(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'themesfs' => '/horde/themes',
                'themesuri' => '/themes/horde',
            ],
            'jonah' => [
                'status' => 'active',
                'themesfs' => '/jonah/themes',
                'themesuri' => '/themes/jonah',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/jonah/themes/dark/graphics/new.png';
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $url = $assets->getGraphicUrl('new.png', 'jonah', 'dark');

        $this->assertEquals('/themes/jonah/dark/graphics/new.png', $url);
    }

    public function testGetGraphicUrlFallsToAppDefault(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'themesfs' => '/horde/themes',
                'themesuri' => '/themes/horde',
            ],
            'jonah' => [
                'status' => 'active',
                'themesfs' => '/jonah/themes',
                'themesuri' => '/themes/jonah',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/jonah/themes/default/graphics/new.png';
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $url = $assets->getGraphicUrl('new.png', 'jonah', 'dark');

        $this->assertEquals('/themes/jonah/default/graphics/new.png', $url);
    }

    public function testGetGraphicUrlFallsToHordeTheme(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'themesfs' => '/horde/themes',
                'themesuri' => '/themes/horde',
            ],
            'jonah' => [
                'status' => 'active',
                'themesfs' => '/jonah/themes',
                'themesuri' => '/themes/jonah',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/horde/themes/dark/graphics/edit.png';
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $url = $assets->getGraphicUrl('edit.png', 'jonah', 'dark');

        $this->assertEquals('/themes/horde/dark/graphics/edit.png', $url);
    }

    public function testGetGraphicUrlFallsToHordeDefault(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'themesfs' => '/horde/themes',
                'themesuri' => '/themes/horde',
            ],
            'jonah' => [
                'status' => 'active',
                'themesfs' => '/jonah/themes',
                'themesuri' => '/themes/jonah',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/horde/themes/default/graphics/edit.png';
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $url = $assets->getGraphicUrl('edit.png', 'jonah', 'default');

        $this->assertEquals('/themes/horde/default/graphics/edit.png', $url);
    }

    public function testGetGraphicUrlReturnsEmptyWhenNotFound(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'themesfs' => '/themes-fs/horde',
                'themesuri' => '/themes/horde',
            ],
            'jonah' => [
                'status' => 'active',
                'themesfs' => '/themes-fs/jonah',
                'themesuri' => '/themes/jonah',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturn(false);

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $url = $assets->getGraphicUrl('missing.png', 'jonah', 'default');

        $this->assertSame('', $url);
    }

    public function testGetGraphicUrlHordeAppSkipsAppCandidates(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'themesfs' => '/horde/themes',
                'themesuri' => '/themes/horde',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/horde/themes/default/graphics/logo.png';
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $url = $assets->getGraphicUrl('logo.png', 'horde', 'default');

        $this->assertEquals('/themes/horde/default/graphics/logo.png', $url);
    }

    public function testGetGraphicUrlSubdirectory(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'themesfs' => '/horde/themes',
                'themesuri' => '/themes/horde',
            ],
            'jonah' => [
                'status' => 'active',
                'themesfs' => '/jonah/themes',
                'themesuri' => '/themes/jonah',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/jonah/themes/default/graphics/mime/pdf.png';
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $url = $assets->getGraphicUrl('mime/pdf.png', 'jonah', 'default');

        $this->assertEquals('/themes/jonah/default/graphics/mime/pdf.png', $url);
    }

    public function testGetParamFallsBackToHorde(): void
    {
        $state = $this->buildState([
            'horde' => [
                'status' => 'active',
                'jsfs' => '/horde/js',
                'jsuri' => '/js/horde',
            ],
            'nag' => [
                'status' => 'active',
            ],
        ]);

        $this->filesystemStub->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/horde/js/test.js';
            });

        $assets = new ResponsiveAssets($state, $this->filesystemStub);
        $urls = $assets->getJsUrls('nag', ['test.js']);

        // Both horde and nag resolve to horde's jsuri (nag has no jsfs/jsuri,
        // so getParam falls back to horde for both)
        $this->assertCount(2, $urls);
        $this->assertEquals('/js/horde/test.js', $urls[0]);
        $this->assertEquals('/js/horde/test.js', $urls[1]);
    }
}
