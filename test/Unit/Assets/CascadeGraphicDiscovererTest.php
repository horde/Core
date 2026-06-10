<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\AssetFilesystem;
use Horde\Core\Assets\CascadeGraphicDiscoverer;
use Horde\Core\Assets\GraphicDiscoverer;
use Horde\Core\Path\PathBuilderInterface;
use Horde\Core\Uri\UriBuilderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;
use SplFileInfo;

#[CoversClass(CascadeGraphicDiscoverer::class)]
class CascadeGraphicDiscovererTest extends TestCase
{
    /** @var array<string, string> */
    private const APP_THEMES_DIRS = [
        'horde' => '/srv/www/horde/themes/horde',
        'turba' => '/srv/www/horde/turba/themes/turba',
    ];

    /** @var array<string, string> */
    private const APP_THEMES_URIS = [
        'horde' => '/horde/themes/horde',
        'turba' => '/horde/turba/themes/turba',
    ];

    #[Test]
    public function implementsGraphicDiscoverer(): void
    {
        $filesystem = $this->createMock(AssetFilesystem::class);
        $filesystem->expects(self::never())->method(self::anything());

        $discoverer = new CascadeGraphicDiscoverer(
            $this->createPathMock(self::APP_THEMES_DIRS),
            $this->createUriMock(self::APP_THEMES_URIS),
            $filesystem,
        );

        self::assertInstanceOf(GraphicDiscoverer::class, $discoverer);
    }

    #[Test]
    public function hordeDefaultFallback(): void
    {
        $filesystem = $this->createMock(AssetFilesystem::class);
        $filesystem->expects(self::atLeastOnce())
            ->method('fileExists')
            ->willReturnCallback(
                static fn(string $path): bool
                    => $path === '/srv/www/horde/themes/horde/default/graphics/logo.png',
            );

        $discoverer = new CascadeGraphicDiscoverer(
            $this->createPathMock(self::APP_THEMES_DIRS),
            $this->createUriMock(self::APP_THEMES_URIS),
            $filesystem,
        );
        $result = $discoverer->resolve('logo.png');

        self::assertSame('/horde/themes/horde/default/graphics/logo.png', $result);
    }

    #[Test]
    public function mostSpecificWins(): void
    {
        $existing = [
            '/srv/www/horde/themes/horde/default/graphics/logo.png' => true,
            '/srv/www/horde/turba/themes/turba/silver/graphics/logo.png' => true,
        ];
        $filesystem = $this->createMock(AssetFilesystem::class);
        // First candidate (turba/silver) matches and short-circuits.
        $filesystem->expects(self::once())
            ->method('fileExists')
            ->with('/srv/www/horde/turba/themes/turba/silver/graphics/logo.png')
            ->willReturn(true);

        $discoverer = new CascadeGraphicDiscoverer(
            $this->createPathMock(self::APP_THEMES_DIRS),
            $this->createUriMock(self::APP_THEMES_URIS),
            $filesystem,
        );
        $result = $discoverer->resolve('logo.png', 'silver', 'turba');

        self::assertSame('/horde/turba/themes/turba/silver/graphics/logo.png', $result);
        // Suppress unused-variable static-analysis warning while keeping the
        // declared expectations explicit above.
        unset($existing);
    }

    #[Test]
    public function appDefaultBeforeHordeTheme(): void
    {
        $filesystem = $this->createMock(AssetFilesystem::class);
        // Cascade order for ('silver', 'turba'): turba/silver, turba/default,
        // horde/silver, horde/default. turba/silver misses, turba/default hits.
        $filesystem->expects(self::exactly(2))
            ->method('fileExists')
            ->willReturnCallback(
                static fn(string $path): bool
                    => $path === '/srv/www/horde/turba/themes/turba/default/graphics/logo.png',
            );

        $discoverer = new CascadeGraphicDiscoverer(
            $this->createPathMock(self::APP_THEMES_DIRS),
            $this->createUriMock(self::APP_THEMES_URIS),
            $filesystem,
        );
        $result = $discoverer->resolve('logo.png', 'silver', 'turba');

        self::assertSame('/horde/turba/themes/turba/default/graphics/logo.png', $result);
    }

    #[Test]
    public function noMatchReturnsNull(): void
    {
        $filesystem = $this->createMock(AssetFilesystem::class);
        // Every candidate is probed: turba/silver, turba/default, horde/silver,
        // horde/default. None exist.
        $filesystem->expects(self::exactly(4))
            ->method('fileExists')
            ->willReturn(false);

        $discoverer = new CascadeGraphicDiscoverer(
            $this->createPathMock(self::APP_THEMES_DIRS),
            $this->createUriMock(self::APP_THEMES_URIS),
            $filesystem,
        );
        $result = $discoverer->resolve('missing.png', 'silver', 'turba');

        self::assertNull($result);
    }

    #[Test]
    public function hordeAppSkipsAppLevels(): void
    {
        $filesystem = $this->createMock(AssetFilesystem::class);
        // For app=horde the cascade collapses to just (horde, silver) then
        // (horde, default). The first probe hits.
        $filesystem->expects(self::once())
            ->method('fileExists')
            ->with('/srv/www/horde/themes/horde/silver/graphics/icon.png')
            ->willReturn(true);

        $discoverer = new CascadeGraphicDiscoverer(
            $this->createPathMock(self::APP_THEMES_DIRS),
            $this->createUriMock(self::APP_THEMES_URIS),
            $filesystem,
        );
        $result = $discoverer->resolve('icon.png', 'silver', 'horde');

        self::assertSame('/horde/themes/horde/silver/graphics/icon.png', $result);
    }

    #[Test]
    public function defaultThemeSkipsThemeOverrides(): void
    {
        $filesystem = $this->createMock(AssetFilesystem::class);
        // For theme=default, app=turba: cascade is (turba, default) then
        // (horde, default). turba/default misses, horde/default hits.
        $filesystem->expects(self::exactly(2))
            ->method('fileExists')
            ->willReturnCallback(
                static fn(string $path): bool
                    => $path === '/srv/www/horde/themes/horde/default/graphics/icon.png',
            );

        $discoverer = new CascadeGraphicDiscoverer(
            $this->createPathMock(self::APP_THEMES_DIRS),
            $this->createUriMock(self::APP_THEMES_URIS),
            $filesystem,
        );
        $result = $discoverer->resolve('icon.png', 'default', 'turba');

        self::assertSame('/horde/themes/horde/default/graphics/icon.png', $result);
    }

    #[Test]
    public function subpathInFilename(): void
    {
        $filesystem = $this->createMock(AssetFilesystem::class);
        $filesystem->expects(self::once())
            ->method('fileExists')
            ->with('/srv/www/horde/themes/horde/default/graphics/mime/pdf.png')
            ->willReturn(true);

        $discoverer = new CascadeGraphicDiscoverer(
            $this->createPathMock(self::APP_THEMES_DIRS),
            $this->createUriMock(self::APP_THEMES_URIS),
            $filesystem,
        );
        $result = $discoverer->resolve('mime/pdf.png');

        self::assertSame('/horde/themes/horde/default/graphics/mime/pdf.png', $result);
    }

    #[Test]
    public function favicon(): void
    {
        $filesystem = $this->createMock(AssetFilesystem::class);
        $filesystem->expects(self::once())
            ->method('fileExists')
            ->with('/srv/www/horde/themes/horde/default/graphics/favicon.ico')
            ->willReturn(true);

        $discoverer = new CascadeGraphicDiscoverer(
            $this->createPathMock(self::APP_THEMES_DIRS),
            $this->createUriMock(self::APP_THEMES_URIS),
            $filesystem,
        );
        $result = $discoverer->resolve('favicon.ico');

        self::assertSame('/horde/themes/horde/default/graphics/favicon.ico', $result);
    }

    /** @param array<string, string> $appThemesDirs */
    private function createPathMock(array $appThemesDirs): PathBuilderInterface
    {
        return new class ($appThemesDirs) implements PathBuilderInterface {
            private string $path = '';

            /** @param array<string, string> $dirs */
            public function __construct(private readonly array $dirs) {}

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
                $clone->path = $this->dirs[$app] ?? '';
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

    /** @param array<string, string> $appThemesUris */
    private function createUriMock(array $appThemesUris): UriBuilderInterface
    {
        return new class ($appThemesUris) implements UriBuilderInterface, Stringable {
            private string $path = '';

            /** @param array<string, string> $uris */
            public function __construct(private readonly array $uris) {}

            public function withAppWebroot(string $app): static
            {
                return $this;
            }

            public function withThemesUri(string $app): static
            {
                $clone = clone $this;
                $clone->path = $this->uris[$app] ?? '';
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

            public function withQueryParams(array $params): static
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
                $c = clone $this;
                $c->path = $path;
                return $c;
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
