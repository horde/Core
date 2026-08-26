<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\AssetFilesystem;
use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\Assets\JsDiscoveryRequest;
use Horde\Core\Assets\LocalAssetFilesystem;
use Horde\Core\Assets\ThemeInfoReader;
use Horde\Core\Assets\ThemeJsDiscoverer;
use Horde\Core\Path\PathBuilderInterface;
use Horde\Core\Uri\UriBuilderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Stringable;

#[CoversClass(ThemeJsDiscoverer::class)]
class ThemeJsDiscovererTest extends TestCase
{
    private string $root;
    private PathBuilderInterface $pathBuilder;
    private UriBuilderInterface $uriBuilder;
    private AssetFilesystem $filesystem;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/horde-theme-js-' . uniqid('', true);
        mkdir($this->root, 0777, true);
        $this->pathBuilder = $this->createPathMock($this->root);
        $this->uriBuilder = $this->createUriMock();
        $this->filesystem = new LocalAssetFilesystem();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    #[Test]
    public function implementsJsDiscoverer(): void
    {
        $reader = $this->createMock(ThemeInfoReader::class);
        $reader->expects(self::never())->method('readScripts');

        $discoverer = $this->discoverer($reader);

        self::assertInstanceOf(JsDiscoverer::class, $discoverer);
    }

    #[Test]
    public function flatResolveStillUsesAppJsDir(): void
    {
        $this->createFile('horde/js/topbar.js');

        $reader = $this->createMock(ThemeInfoReader::class);
        $reader->expects(self::never())->method('readScripts');

        $discoverer = $this->discoverer($reader);

        self::assertSame('/uri/horde/js/topbar.js', $discoverer->resolve('topbar.js'));
        self::assertNull($discoverer->resolve('missing.js'));
    }

    #[Test]
    public function discoverThemeUsesThemeDeclarations(): void
    {
        $this->createFile('horde/themes/silver/theme.js');

        $reader = $this->createMock(ThemeInfoReader::class);
        $reader->expects(self::atLeastOnce())
            ->method('readScripts')
            ->with('horde', 'silver')
            ->willReturn(['theme.js']);

        $result = $this->discoverer($reader)
            ->discoverTheme(new JsDiscoveryRequest(app: 'horde', theme: 'silver'));

        self::assertCount(1, $result);
        self::assertSame('/uri/horde/themes/silver/theme.js', $result->toArray()[0]->uri);
    }

    #[Test]
    public function discoverThemeCascadesHordeThenApp(): void
    {
        $this->createFile('horde/themes/silver/base.js');
        $this->createFile('turba/themes/silver/app.js');

        $reader = $this->createMock(ThemeInfoReader::class);
        $reader->expects(self::atLeastOnce())
            ->method('readScripts')
            ->willReturnMap([
                ['horde', 'silver', ['base.js']],
                ['turba', 'silver', ['app.js']],
            ]);

        $result = $this->discoverer($reader)
            ->discoverTheme(new JsDiscoveryRequest(app: 'turba', theme: 'silver'));

        $uris = array_map(static fn($e): string => $e->uri, $result->toArray());

        self::assertSame([
            '/uri/horde/themes/silver/base.js',
            '/uri/turba/themes/silver/app.js',
        ], $uris);
    }

    #[Test]
    public function discoverThemeSkipsMissingDeclaredFile(): void
    {
        $reader = $this->createMock(ThemeInfoReader::class);
        $reader->expects(self::atLeastOnce())
            ->method('readScripts')
            ->with('horde', 'silver')
            ->willReturn(['ghost.js']);

        $result = $this->discoverer($reader)
            ->discoverTheme(new JsDiscoveryRequest(app: 'horde', theme: 'silver'));

        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function discoverThemeEmptyWhenNoDeclarations(): void
    {
        $reader = $this->createMock(ThemeInfoReader::class);
        $reader->expects(self::atLeastOnce())
            ->method('readScripts')
            ->with('horde', 'default')
            ->willReturn([]);

        $result = $this->discoverer($reader)
            ->discoverTheme(new JsDiscoveryRequest(app: 'horde', theme: 'default'));

        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function discoverThemeExplicitFilesBypassReader(): void
    {
        $this->createFile('horde/themes/silver/given.js');

        $reader = $this->createMock(ThemeInfoReader::class);
        $reader->expects(self::never())->method('readScripts');

        $result = $this->discoverer($reader)->discoverTheme(
            new JsDiscoveryRequest(files: ['given.js'], app: 'horde', theme: 'silver'),
        );

        self::assertCount(1, $result);
        self::assertSame('/uri/horde/themes/silver/given.js', $result->toArray()[0]->uri);
    }

    private function discoverer(ThemeInfoReader $reader): ThemeJsDiscoverer
    {
        return new ThemeJsDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem, $reader);
    }

    private function createFile(string $relative): void
    {
        $full = $this->root . '/' . $relative;
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($full, '// js');
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        foreach ($items === false ? [] : $items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeTree($full) : unlink($full);
        }
        rmdir($path);
    }

    private function createPathMock(string $root): PathBuilderInterface
    {
        return new class ($root) implements PathBuilderInterface {
            private string $path = '';

            public function __construct(private readonly string $root)
            {
                $this->path = $root;
            }

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
                $clone->path = $this->root . '/' . $app . '/themes';
                return $clone;
            }

            public function withAppJsDir(string $app): static
            {
                $clone = clone $this;
                $clone->path = $this->root . '/' . $app . '/js';
                return $clone;
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

    private function createUriMock(): UriBuilderInterface
    {
        return new class implements UriBuilderInterface, Stringable {
            private string $path = '/uri';

            public function withAppWebroot(string $app): static
            {
                return $this;
            }

            public function withThemesUri(string $app): static
            {
                $clone = clone $this;
                $clone->path = '/uri/' . $app . '/themes';
                return $clone;
            }

            public function withJsUri(string $app): static
            {
                $clone = clone $this;
                $clone->path = '/uri/' . $app . '/js';
                return $clone;
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
