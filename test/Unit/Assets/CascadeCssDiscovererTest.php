<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\AssetFilesystem;
use Horde\Core\Assets\CascadeCssDiscoverer;
use Horde\Core\Assets\CssAssetEntry;
use Horde\Core\Assets\CssDiscoverer;
use Horde\Core\Assets\CssDiscoveryRequest;
use Horde\Core\Assets\CssHookProvider;
use Horde\Core\Assets\TextDirectionProvider;
use Horde\Core\Path\PathBuilderInterface;
use Horde\Core\Uri\UriBuilderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;
use SplFileInfo;

#[CoversClass(CascadeCssDiscoverer::class)]
class CascadeCssDiscovererTest extends TestCase
{
    /** @var list<string> */
    private array $existingPaths = [];

    private PathBuilderInterface $pathBuilder;
    private UriBuilderInterface $uriBuilder;
    private AssetFilesystem $filesystem;

    protected function setUp(): void
    {
        $this->existingPaths = [];

        $this->pathBuilder = $this->createPathBuilderMock('/srv/www/horde');
        $this->uriBuilder = $this->createUriBuilderMock('/horde');
        $this->filesystem = $this->createMock(AssetFilesystem::class);
        $this->filesystem->method('fileExists')->willReturnCallback(
            fn(string $path): bool => in_array($path, $this->existingPaths, true)
        );
    }

    #[Test]
    public function implementsCssDiscoverer(): void
    {
        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);

        self::assertInstanceOf(CssDiscoverer::class, $discoverer);
    }

    #[Test]
    public function hordeDefaultOnly(): void
    {
        $this->existingPaths = ['/srv/www/horde/themes/horde/default/screen.css'];

        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->discover(new CssDiscoveryRequest());

        self::assertCount(1, $result);
        $entry = $result->toArray()[0];
        self::assertSame('/srv/www/horde/themes/horde/default/screen.css', $entry->fsPath);
        self::assertSame('/horde/themes/horde/default/screen.css', $entry->uri);
        self::assertSame('horde', $entry->app);
    }

    #[Test]
    public function fullFourLevelCascade(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/themes/horde/default/screen.css',
            '/srv/www/horde/themes/horde/silver/screen.css',
            '/srv/www/horde/turba/themes/turba/default/screen.css',
            '/srv/www/horde/turba/themes/turba/silver/screen.css',
        ];

        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->discover(new CssDiscoveryRequest(
            app: 'turba',
            theme: 'silver',
        ));

        self::assertCount(4, $result);
        $apps = array_map(fn(CssAssetEntry $e) => $e->app, $result->toArray());
        self::assertSame(['horde', 'horde', 'turba', 'turba'], $apps);

        $uris = array_map(fn(CssAssetEntry $e) => $e->uri, $result->toArray());
        self::assertSame([
            '/horde/themes/horde/default/screen.css',
            '/horde/themes/horde/silver/screen.css',
            '/horde/turba/themes/turba/default/screen.css',
            '/horde/turba/themes/turba/silver/screen.css',
        ], $uris);
    }

    #[Test]
    public function defaultThemeSkipsThemeOverride(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/themes/horde/default/screen.css',
            '/srv/www/horde/turba/themes/turba/default/screen.css',
        ];

        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->discover(new CssDiscoveryRequest(
            app: 'turba',
            theme: 'default',
        ));

        self::assertCount(2, $result);
        $uris = array_map(fn(CssAssetEntry $e) => $e->uri, $result->toArray());
        self::assertSame([
            '/horde/themes/horde/default/screen.css',
            '/horde/turba/themes/turba/default/screen.css',
        ], $uris);
    }

    #[Test]
    public function hordeAppSkipsAppLevels(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/themes/horde/default/screen.css',
            '/srv/www/horde/themes/horde/silver/screen.css',
        ];

        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->discover(new CssDiscoveryRequest(
            app: 'horde',
            theme: 'silver',
        ));

        self::assertCount(2, $result);
    }

    #[Test]
    public function subViewOverlay(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/themes/horde/default/screen.css',
            '/srv/www/horde/themes/horde/default/dynamic/screen.css',
        ];

        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->discover(new CssDiscoveryRequest(
            subView: 'dynamic',
        ));

        self::assertCount(2, $result);
        $uris = array_map(fn(CssAssetEntry $e) => $e->uri, $result->toArray());
        self::assertSame([
            '/horde/themes/horde/default/screen.css',
            '/horde/themes/horde/default/dynamic/screen.css',
        ], $uris);
    }

    #[Test]
    public function rtlAppendedWhenProviderReturnsTrue(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/themes/horde/default/screen.css',
            '/srv/www/horde/themes/horde/default/rtl.css',
        ];

        $rtl = $this->createMock(TextDirectionProvider::class);
        $rtl->method('isRtl')->willReturn(true);

        $discoverer = new CascadeCssDiscoverer(
            $this->pathBuilder,
            $this->uriBuilder,
            $this->filesystem,
            $rtl,
        );
        $result = $discoverer->discover(new CssDiscoveryRequest());

        self::assertCount(2, $result);
        $uris = array_map(fn(CssAssetEntry $e) => $e->uri, $result->toArray());
        self::assertSame([
            '/horde/themes/horde/default/screen.css',
            '/horde/themes/horde/default/rtl.css',
        ], $uris);
    }

    #[Test]
    public function rtlSkippedWhenProviderReturnsFalse(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/themes/horde/default/screen.css',
            '/srv/www/horde/themes/horde/default/rtl.css',
        ];

        $rtl = $this->createMock(TextDirectionProvider::class);
        $rtl->method('isRtl')->willReturn(false);

        $discoverer = new CascadeCssDiscoverer(
            $this->pathBuilder,
            $this->uriBuilder,
            $this->filesystem,
            $rtl,
        );
        $result = $discoverer->discover(new CssDiscoveryRequest());

        self::assertCount(1, $result);
    }

    #[Test]
    public function rtlSkippedWhenProviderIsNull(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/themes/horde/default/screen.css',
            '/srv/www/horde/themes/horde/default/rtl.css',
        ];

        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->discover(new CssDiscoveryRequest());

        self::assertCount(1, $result);
    }

    #[Test]
    public function hookFilesAppended(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/themes/horde/default/screen.css',
        ];

        $hooks = $this->createMock(CssHookProvider::class);
        $hooks->method('getHookFiles')->willReturn([
            '/custom/hook.css' => '/custom/hook.css',
        ]);

        $discoverer = new CascadeCssDiscoverer(
            $this->pathBuilder,
            $this->uriBuilder,
            $this->filesystem,
            null,
            $hooks,
        );
        $result = $discoverer->discover(new CssDiscoveryRequest());

        self::assertCount(2, $result);
        $last = $result->toArray()[1];
        self::assertSame('/custom/hook.css', $last->fsPath);
        self::assertSame('/custom/hook.css', $last->uri);
    }

    #[Test]
    public function hooksSkippedWhenNull(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/themes/horde/default/screen.css',
        ];

        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->discover(new CssDiscoveryRequest());

        self::assertCount(1, $result);
    }

    #[Test]
    public function missingFilesOmitted(): void
    {
        $this->existingPaths = [];

        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->discover(new CssDiscoveryRequest(
            app: 'turba',
            theme: 'silver',
        ));

        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function multipleFilesInRequest(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/themes/horde/default/screen.css',
            '/srv/www/horde/themes/horde/default/print.css',
        ];

        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->discover(new CssDiscoveryRequest(
            files: ['screen.css', 'print.css'],
        ));

        self::assertCount(2, $result);
        $uris = array_map(fn(CssAssetEntry $e) => $e->uri, $result->toArray());
        self::assertSame([
            '/horde/themes/horde/default/screen.css',
            '/horde/themes/horde/default/print.css',
        ], $uris);
    }

    #[Test]
    public function resultMetadata(): void
    {
        $this->existingPaths = [];

        $discoverer = new CascadeCssDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->discover(new CssDiscoveryRequest(
            app: 'turba',
            theme: 'silver',
        ));

        self::assertSame('silver', $result->getTheme());
        self::assertSame('turba', $result->getApp());
    }

    private function createPathBuilderMock(string $baseFs): PathBuilderInterface
    {
        $appThemesDirs = [
            'horde' => $baseFs . '/themes/horde',
            'turba' => $baseFs . '/turba/themes/turba',
            'imp' => $baseFs . '/imp/themes/imp',
        ];

        return new class ($appThemesDirs) implements PathBuilderInterface {
            private string $path = '';

            /** @param array<string, string> $appThemesDirs */
            public function __construct(private readonly array $appThemesDirs) {}

            public function withComponentRoot(): static
            {
                return $this;
            }

            public function withAppFileroot(string $app): static
            {
                return $this;
            }

            public function withAppThemesDir(string $app): static
            {
                $clone = clone $this;
                $clone->path = $this->appThemesDirs[$app] ?? '';
                return $clone;
            }

            public function withAppJsDir(string $app): static
            {
                return $this;
            }

            public function withStaticDir(): static
            {
                return $this;
            }

            public function withConfigDir(?string $app = null): static
            {
                return $this;
            }

            public function withTmpDir(): static
            {
                return $this;
            }

            public function withSlug(string $slug): static
            {
                $clone = clone $this;
                $clone->path = $this->path . '/' . $slug;
                return $clone;
            }

            public function withPart(string $part): static
            {
                $clone = clone $this;
                $clone->path = $this->path . '/' . $part;
                return $clone;
            }

            public function toSplFileInfo(): SplFileInfo
            {
                return new SplFileInfo($this->path);
            }

            public function __toString(): string
            {
                return $this->path;
            }
        };
    }

    private function createUriBuilderMock(string $baseUri): UriBuilderInterface
    {
        $appThemesUris = [
            'horde' => $baseUri . '/themes/horde',
            'turba' => $baseUri . '/turba/themes/turba',
            'imp' => $baseUri . '/imp/themes/imp',
        ];

        return new class ($appThemesUris) implements UriBuilderInterface, Stringable {
            private string $path = '';

            /** @param array<string, string> $appThemesUris */
            public function __construct(private readonly array $appThemesUris) {}

            public function withAppWebroot(string $app): static
            {
                return $this;
            }

            public function withThemesUri(string $app): static
            {
                $clone = clone $this;
                $clone->path = $this->appThemesUris[$app] ?? '';
                return $clone;
            }

            public function withJsUri(string $app): static
            {
                return $this;
            }

            public function withStaticUri(): static
            {
                return $this;
            }

            public function withNamedRoute(string $app, string $name, array $params = []): static
            {
                return $this;
            }

            public function withSlug(string $slug): static
            {
                $clone = clone $this;
                $clone->path = $this->path . '/' . $slug;
                return $clone;
            }

            public function withPart(string $part): static
            {
                $clone = clone $this;
                $clone->path = $this->path . '/' . $part;
                return $clone;
            }

            public function toHordeUrl(): \Horde\Url\Url
            {
                return new \Horde\Url\Url($this->path);
            }

            public function getScheme(): string
            {
                return '';
            }

            public function getAuthority(): string
            {
                return '';
            }

            public function getUserInfo(): string
            {
                return '';
            }

            public function getHost(): string
            {
                return '';
            }

            public function getPort(): ?int
            {
                return null;
            }

            public function getPath(): string
            {
                return $this->path;
            }

            public function getQuery(): string
            {
                return '';
            }

            public function getFragment(): string
            {
                return '';
            }

            public function withScheme(string $scheme): static
            {
                return $this;
            }

            public function withUserInfo(string $user, ?string $password = null): static
            {
                return $this;
            }

            public function withHost(string $host): static
            {
                return $this;
            }

            public function withPort(?int $port): static
            {
                return $this;
            }

            public function withPath(string $path): static
            {
                $clone = clone $this;
                $clone->path = $path;
                return $clone;
            }

            public function withQuery(string $query): static
            {
                return $this;
            }

            public function withFragment(string $fragment): static
            {
                return $this;
            }

            public function __toString(): string
            {
                return $this->path;
            }
        };
    }
}
