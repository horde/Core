<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\AssetFilesystem;
use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\Assets\PathBasedJsDiscoverer;
use Horde\Core\Path\PathBuilderInterface;
use Horde\Core\Uri\UriBuilderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;
use SplFileInfo;

#[CoversClass(PathBasedJsDiscoverer::class)]
class PathBasedJsDiscovererTest extends TestCase
{
    /** @var list<string> */
    private array $existingPaths = [];

    private PathBuilderInterface $pathBuilder;
    private UriBuilderInterface $uriBuilder;
    private AssetFilesystem $filesystem;

    protected function setUp(): void
    {
        $this->existingPaths = [];

        $appJsDirs = [
            'horde' => '/srv/www/horde/js',
            'turba' => '/srv/www/horde/turba/js',
        ];
        $appJsUris = [
            'horde' => '/horde/js',
            'turba' => '/horde/turba/js',
        ];

        $this->pathBuilder = $this->createPathMock($appJsDirs);
        $this->uriBuilder = $this->createUriMock($appJsUris);
        $this->filesystem = $this->createMock(AssetFilesystem::class);
        $this->filesystem->method('fileExists')->willReturnCallback(
            fn(string $path): bool => in_array($path, $this->existingPaths, true)
        );
    }

    #[Test]
    public function implementsJsDiscoverer(): void
    {
        $discoverer = new PathBasedJsDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);

        self::assertInstanceOf(JsDiscoverer::class, $discoverer);
    }

    #[Test]
    public function resolveExistingFile(): void
    {
        $this->existingPaths = ['/srv/www/horde/js/topbar.js'];

        $discoverer = new PathBasedJsDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->resolve('topbar.js');

        self::assertSame('/horde/js/topbar.js', $result);
    }

    #[Test]
    public function resolveMissingFile(): void
    {
        $this->existingPaths = [];

        $discoverer = new PathBasedJsDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->resolve('nonexistent.js');

        self::assertNull($result);
    }

    #[Test]
    public function resolveWithApp(): void
    {
        $this->existingPaths = ['/srv/www/horde/turba/js/contacts.js'];

        $discoverer = new PathBasedJsDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->resolve('contacts.js', 'turba');

        self::assertSame('/horde/turba/js/contacts.js', $result);
    }

    #[Test]
    public function resolveManyMixed(): void
    {
        $this->existingPaths = [
            '/srv/www/horde/js/topbar.js',
        ];

        $discoverer = new PathBasedJsDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->resolveMany(['topbar.js', 'sidebar.js']);

        self::assertSame([
            'topbar.js' => '/horde/js/topbar.js',
            'sidebar.js' => null,
        ], $result);
    }

    #[Test]
    public function resolveManyEmpty(): void
    {
        $discoverer = new PathBasedJsDiscoverer($this->pathBuilder, $this->uriBuilder, $this->filesystem);
        $result = $discoverer->resolveMany([]);

        self::assertSame([], $result);
    }

    /** @param array<string, string> $appJsDirs */
    private function createPathMock(array $appJsDirs): PathBuilderInterface
    {
        return new class ($appJsDirs) implements PathBuilderInterface {
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
                return $this;
            }

            public function withAppJsDir(string $app): static
            {
                $clone = clone $this;
                $clone->path = $this->dirs[$app] ?? '';
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

    /** @param array<string, string> $appJsUris */
    private function createUriMock(array $appJsUris): UriBuilderInterface
    {
        return new class ($appJsUris) implements UriBuilderInterface, Stringable {
            private string $path = '';

            /** @param array<string, string> $uris */
            public function __construct(private readonly array $uris) {}

            public function withAppWebroot(string $app): static
            {
                return $this;
            }
            public function withThemesUri(string $app): static
            {
                return $this;
            }

            public function withJsUri(string $app): static
            {
                $clone = clone $this;
                $clone->path = $this->uris[$app] ?? '';
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
