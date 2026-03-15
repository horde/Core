<?php

declare(strict_types=1);

namespace Horde\Core\Test\Assets;

use Horde\Core\Assets\ResponsiveAssets;
use Horde\Core\Assets\ResponsiveAssetsFilesystem;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde_Registry;

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
    private $registryMock;
    private $filesystemMock;

    protected function setUp(): void
    {
        $this->registryMock = $this->createMock(Horde_Registry::class);
        $this->filesystemMock = $this->createMock(ResponsiveAssetsFilesystem::class);
    }

    public function testGetCssUrlsHordeOnly(): void
    {
        // Setup registry mock
        $this->registryMock->method('get')
            ->willReturnCallback(function ($key, $app) {
                if ($key === 'themesfs' && $app === 'horde') {
                    return '/horde/themes';
                }
                if ($key === 'themesuri' && $app === 'horde') {
                    return '/themes/horde';
                }
                return null;
            });

        $this->registryMock->method('getApp')
            ->willReturn('horde');

        // Setup filesystem mock - file exists
        $this->filesystemMock->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/horde/themes/default/responsive.css';
            });

        // Create assets helper
        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);

        // Get CSS URLs
        $urls = $assets->getCssUrls('default', 'horde');

        // Verify
        $this->assertIsArray($urls);
        $this->assertCount(1, $urls);
        $this->assertEquals('/themes/horde/default/responsive.css', $urls[0]);
    }

    public function testGetCssUrlsWithAppCascade(): void
    {
        // Setup: Both horde and kronolith have responsive.css
        $this->registryMock->method('get')
            ->willReturnCallback(function ($key, $app) {
                if ($key === 'themesfs') {
                    return $app === 'horde' ? '/horde/themes' : '/kronolith/themes';
                }
                if ($key === 'themesuri') {
                    return $app === 'horde' ? '/themes/horde' : '/themes/kronolith';
                }
                return null;
            });

        $this->registryMock->method('getApp')
            ->willReturn('kronolith');

        // Both files exist
        $this->filesystemMock->method('fileExists')
            ->willReturnCallback(function ($path) {
                return str_contains($path, 'responsive.css');
            });

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $urls = $assets->getCssUrls('default', 'kronolith');

        // Verify cascade order: horde first, app second
        $this->assertCount(2, $urls);
        $this->assertEquals('/themes/horde/default/responsive.css', $urls[0]);
        $this->assertEquals('/themes/kronolith/default/responsive.css', $urls[1]);
    }

    public function testGetCssUrlsFileNotFound(): void
    {
        // Setup: File doesn't exist
        $this->filesystemMock->method('fileExists')
            ->willReturn(false);

        $this->registryMock->method('get')
            ->willReturn('/horde/themes');

        $this->registryMock->method('getApp')
            ->willReturn('horde');

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $urls = $assets->getCssUrls('default', 'horde');

        // No URLs returned when files don't exist
        $this->assertEmpty($urls);
    }

    public function testGetCssUrlsOnlyAppFileExists(): void
    {
        // Setup: Only app file exists, not horde base
        $this->registryMock->method('get')
            ->willReturnCallback(function ($key, $app) {
                if ($key === 'themesfs') {
                    return $app === 'horde' ? '/horde/themes' : '/kronolith/themes';
                }
                if ($key === 'themesuri') {
                    return $app === 'horde' ? '/themes/horde' : '/themes/kronolith';
                }
                return null;
            });

        $this->registryMock->method('getApp')
            ->willReturn('kronolith');

        // Only kronolith file exists
        $this->filesystemMock->method('fileExists')
            ->willReturnCallback(function ($path) {
                return str_contains($path, '/kronolith/');
            });

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $urls = $assets->getCssUrls('default', 'kronolith');

        // Only app URL returned
        $this->assertCount(1, $urls);
        $this->assertEquals('/themes/kronolith/default/responsive.css', $urls[0]);
    }

    public function testGetJsUrlsHordeOnly(): void
    {
        // Setup registry mock
        $this->registryMock->method('get')
            ->willReturnCallback(function ($key, $app) {
                if ($key === 'jsfs' && $app === 'horde') {
                    return '/horde/js';
                }
                if ($key === 'jsuri' && $app === 'horde') {
                    return '/js/horde';
                }
                return null;
            });

        $this->registryMock->method('getApp')
            ->willReturn('horde');

        // File exists
        $this->filesystemMock->method('fileExists')
            ->willReturnCallback(function ($path) {
                return $path === '/horde/js/login-form.js';
            });

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $urls = $assets->getJsUrls(['login-form.js'], 'horde');

        // Verify
        $this->assertIsArray($urls);
        $this->assertCount(1, $urls);
        $this->assertEquals('/js/horde/login-form.js', $urls[0]);
    }

    public function testGetJsUrlsWithAppCascade(): void
    {
        // Setup: Both horde and kronolith have calendar.js
        $this->registryMock->method('get')
            ->willReturnCallback(function ($key, $app) {
                if ($key === 'jsfs') {
                    return $app === 'horde' ? '/horde/js' : '/kronolith/js';
                }
                if ($key === 'jsuri') {
                    return $app === 'horde' ? '/js/horde' : '/js/kronolith';
                }
                return null;
            });

        $this->registryMock->method('getApp')
            ->willReturn('kronolith');

        // Both files exist
        $this->filesystemMock->method('fileExists')
            ->willReturn(true);

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $urls = $assets->getJsUrls(['calendar.js'], 'kronolith');

        // Verify cascade: horde first, app second
        $this->assertCount(2, $urls);
        $this->assertEquals('/js/horde/calendar.js', $urls[0]);
        $this->assertEquals('/js/kronolith/calendar.js', $urls[1]);
    }

    public function testGetJsUrlsMultipleFiles(): void
    {
        // Setup
        $this->registryMock->method('get')
            ->willReturnCallback(function ($key, $app) {
                if ($key === 'jsfs') {
                    return '/horde/js';
                }
                if ($key === 'jsuri') {
                    return '/js/horde';
                }
                return null;
            });

        $this->registryMock->method('getApp')
            ->willReturn('horde');

        // All files exist
        $this->filesystemMock->method('fileExists')
            ->willReturn(true);

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $urls = $assets->getJsUrls(['file1.js', 'file2.js', 'file3.js'], 'horde');

        // All URLs returned
        $this->assertCount(3, $urls);
        $this->assertEquals('/js/horde/file1.js', $urls[0]);
        $this->assertEquals('/js/horde/file2.js', $urls[1]);
        $this->assertEquals('/js/horde/file3.js', $urls[2]);
    }

    public function testGetJsUrlsEmptyArray(): void
    {
        $this->registryMock->method('getApp')
            ->willReturn('horde');

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $urls = $assets->getJsUrls([], 'horde');

        // No JS files requested, empty array returned
        $this->assertEmpty($urls);
    }

    public function testGetTheme(): void
    {
        // No preferences set, should return 'default'
        $GLOBALS['prefs'] = null;

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $theme = $assets->getTheme();

        $this->assertEquals('default', $theme);
    }

    public function testGetThemeWithPreference(): void
    {
        // Mock preferences
        $prefsMock = $this->createMock(\Horde_Prefs::class);
        $prefsMock->method('getValue')
            ->willReturnCallback(function ($key) {
                return $key === 'theme' ? 'dark' : null;
            });

        $GLOBALS['prefs'] = $prefsMock;

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $theme = $assets->getTheme();

        $this->assertEquals('dark', $theme);

        // Cleanup
        unset($GLOBALS['prefs']);
    }

    public function testCssFileExistsHandlesException(): void
    {
        // Registry throws exception
        $this->registryMock->method('get')
            ->willThrowException(new \Exception('Registry error'));

        $this->registryMock->method('getApp')
            ->willReturn('horde');

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $urls = $assets->getCssUrls('default', 'horde');

        // Should gracefully handle exception and return empty array
        $this->assertEmpty($urls);
    }

    public function testJsFileExistsHandlesException(): void
    {
        // Registry throws exception
        $this->registryMock->method('get')
            ->willThrowException(new \Exception('Registry error'));

        $this->registryMock->method('getApp')
            ->willReturn('horde');

        $assets = new ResponsiveAssets($this->registryMock, $this->filesystemMock);
        $urls = $assets->getJsUrls(['test.js'], 'horde');

        // Should gracefully handle exception and return empty array
        $this->assertEmpty($urls);
    }
}
