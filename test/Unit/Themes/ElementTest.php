<?php

declare(strict_types=1);

/**
 * Tests for Horde_Themes_Element::fromUri().
 *
 * @category Horde
 * @package  Core
 */

namespace Horde\Core\Test\Unit\Themes;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde_Registry;
use Horde_Themes_Element;

#[CoversClass(Horde_Themes_Element::class)]
class ElementTest extends TestCase
{
    private string $fileroot;

    protected function setUp(): void
    {
        $this->fileroot = realpath(sys_get_temp_dir());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['registry']);
    }

    /**
     * Installs a registry stub answering get() from a per-app array.
     */
    private function setRegistry(array $applications): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->method('listAllApps')->willReturn(array_keys($applications));
        $registry->method('get')->willReturnCallback(
            fn($parameter, $app = null) => $applications[$app][$parameter]
                ?? $applications['horde'][$parameter]
                ?? null
        );
        $GLOBALS['registry'] = $registry;
    }

    /**
     * Composer layout: themes are served outside of the Horde webroot.
     */
    private function composerLayout(): array
    {
        return [
            'horde' => [
                'fileroot' => $this->fileroot,
                'webroot' => '/horde/',
                'jsfs' => '/var/www/horde/web/js/horde/',
                'jsuri' => '/js/horde',
                'staticfs' => '/var/www/horde/web/static/',
                'staticuri' => '/static/',
                'themesfs' => '/var/www/horde/web/themes/horde/',
                'themesuri' => '/themes/horde',
            ],
            'imp' => [
                'themesfs' => '/var/www/horde/web/themes/imp/',
                'themesuri' => '/themes/imp',
            ],
        ];
    }

    public function testThemesUriOutsideWebroot(): void
    {
        $this->setRegistry($this->composerLayout());

        $ob = Horde_Themes_Element::fromUri('/themes/horde/mytheme/base.css');

        $this->assertSame('/var/www/horde/web/themes/horde/mytheme/base.css', $ob->fs);
        $this->assertSame('/themes/horde/mytheme/base.css', $ob->uri);
    }

    public function testApplicationThemesUri(): void
    {
        $this->setRegistry($this->composerLayout());

        $ob = Horde_Themes_Element::fromUri('/themes/imp/mytheme/screen.css');

        $this->assertSame('/var/www/horde/web/themes/imp/mytheme/screen.css', $ob->fs);
    }

    public function testStaticUri(): void
    {
        $this->setRegistry($this->composerLayout());

        $ob = Horde_Themes_Element::fromUri('/static/abc.css');

        $this->assertSame('/var/www/horde/web/static/abc.css', $ob->fs);
    }

    public function testLongestPrefixWins(): void
    {
        $applications = $this->composerLayout();
        $applications['horde']['themesfs'] = '/srv/themes/';
        $applications['horde']['themesuri'] = '/themes';
        $this->setRegistry($applications);

        $ob = Horde_Themes_Element::fromUri('/themes/imp/mytheme/screen.css');

        $this->assertSame('/var/www/horde/web/themes/imp/mytheme/screen.css', $ob->fs);
    }

    public function testPrefixMatchesWholePathSegment(): void
    {
        $this->setRegistry($this->composerLayout());

        $ob = Horde_Themes_Element::fromUri('/themes/horde-extra/base.css');

        $this->assertSame($this->fileroot . '/themes/horde-extra/base.css', $ob->fs);
    }

    public function testFallbackStripsWebrootWithTrailingSlash(): void
    {
        $this->setRegistry(['horde' => [
            'fileroot' => $this->fileroot,
            'webroot' => '/horde/',
        ]]);

        $ob = Horde_Themes_Element::fromUri('/horde/themes/default/screen.css');

        $this->assertSame($this->fileroot . '/themes/default/screen.css', $ob->fs);
    }

    public function testFallbackStripsWebrootWithoutTrailingSlash(): void
    {
        $this->setRegistry(['horde' => [
            'fileroot' => $this->fileroot,
            'webroot' => '/horde',
        ]]);

        $ob = Horde_Themes_Element::fromUri('/horde/themes/default/screen.css');

        $this->assertSame($this->fileroot . '/themes/default/screen.css', $ob->fs);
    }

    public function testFallbackWithRootWebroot(): void
    {
        $this->setRegistry(['horde' => [
            'fileroot' => $this->fileroot,
            'webroot' => '/',
        ]]);

        $ob = Horde_Themes_Element::fromUri('/themes/default/screen.css');

        $this->assertSame($this->fileroot . '/themes/default/screen.css', $ob->fs);
    }
}
