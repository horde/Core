<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Uri;

use Horde\Core\Config\RegistryState;
use Horde\Core\Uri\RouteMapperProvider;
use Horde\Core\Uri\UriBuilder;
use Horde\Core\Uri\UriBuilderInterface;
use Horde\Http\Uri;
use Horde\Routes\Mapper;
use Horde\Url\Url;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(UriBuilder::class)]
class UriBuilderTest extends TestCase
{
    private RegistryState $registry;
    private RouteMapperProvider $routeProvider;

    protected function setUp(): void
    {
        $this->registry = new RegistryState([
            'horde' => [
                'webroot' => '/horde',
                'staticuri' => '/horde/static',
            ],
            'kronolith' => [
                'webroot' => '/horde/kronolith',
                'themesuri' => '/horde/kronolith/themes',
                'jsuri' => '/horde/kronolith/js',
            ],
            'turba' => [
                'webroot' => '/horde/turba',
            ],
        ]);

        $this->routeProvider = new class implements RouteMapperProvider {
            public function getMapper(string $app): ?Mapper
            {
                return null;
            }
        };
    }

    private function builder(string $uri = ''): UriBuilder
    {
        return new UriBuilder($this->registry, $this->routeProvider, null, $uri);
    }

    // --- Type preservation through PSR-7 with* methods ---

    #[Test]
    public function withSchemeReturnsUriBuilder(): void
    {
        $builder = $this->builder();
        $result = $builder->withScheme('https');

        self::assertInstanceOf(UriBuilder::class, $result);
        self::assertSame('https', $result->getScheme());
    }

    #[Test]
    public function withHostReturnsUriBuilder(): void
    {
        $result = $this->builder()->withHost('example.com');

        self::assertInstanceOf(UriBuilder::class, $result);
        self::assertSame('example.com', $result->getHost());
    }

    #[Test]
    public function withPortReturnsUriBuilder(): void
    {
        $result = $this->builder()->withPort(8080);

        self::assertInstanceOf(UriBuilder::class, $result);
        self::assertSame(8080, $result->getPort());
    }

    #[Test]
    public function withPathReturnsUriBuilder(): void
    {
        $result = $this->builder()->withPath('/foo/bar');

        self::assertInstanceOf(UriBuilder::class, $result);
        self::assertSame('/foo/bar', $result->getPath());
    }

    #[Test]
    public function withQueryReturnsUriBuilder(): void
    {
        $result = $this->builder()->withQuery('a=1&b=2');

        self::assertInstanceOf(UriBuilder::class, $result);
        self::assertSame('a=1&b=2', $result->getQuery());
    }

    #[Test]
    public function withFragmentReturnsUriBuilder(): void
    {
        $result = $this->builder()->withFragment('section');

        self::assertInstanceOf(UriBuilder::class, $result);
        self::assertSame('section', $result->getFragment());
    }

    #[Test]
    public function withUserInfoReturnsUriBuilder(): void
    {
        $result = $this->builder()->withUserInfo('alice', 'secret');

        self::assertInstanceOf(UriBuilder::class, $result);
        self::assertSame('alice:secret', $result->getUserInfo());
    }

    // --- Builder state survives PSR-7 with* chains ---

    #[Test]
    public function builderStateSurvivesPsr7Chain(): void
    {
        $result = $this->builder()
            ->withAppWebroot('kronolith')
            ->withScheme('https')
            ->withHost('example.com')
            ->withFragment('details');

        self::assertInstanceOf(UriBuilder::class, $result);
        self::assertSame('https', $result->getScheme());
        self::assertSame('example.com', $result->getHost());
        self::assertSame('/horde/kronolith', $result->getPath());
        self::assertSame('details', $result->getFragment());

        // Builder methods still work after PSR-7 chain
        $next = $result->withSlug('events');
        self::assertInstanceOf(UriBuilder::class, $next);
        self::assertSame('/horde/kronolith/events/', $next->getPath());
    }

    // --- Immutability ---

    #[Test]
    public function withMethodsDoNotMutateOriginal(): void
    {
        $original = $this->builder('https://example.com/base');
        $modified = $original->withAppWebroot('kronolith');

        self::assertSame('/base', $original->getPath());
        self::assertSame('/horde/kronolith', $modified->getPath());
    }

    // --- UriInterface compliance ---

    #[Test]
    public function implementsUriInterface(): void
    {
        self::assertInstanceOf(UriInterface::class, $this->builder());
    }

    #[Test]
    public function implementsUriBuilderInterface(): void
    {
        self::assertInstanceOf(UriBuilderInterface::class, $this->builder());
    }

    // --- withAppWebroot ---

    #[Test]
    public function withAppWebrootFromRegistry(): void
    {
        $result = $this->builder()->withAppWebroot('kronolith');
        self::assertSame('/horde/kronolith', $result->getPath());
    }

    #[Test]
    public function withAppWebrootDefaultsToSlashApp(): void
    {
        $registry = new RegistryState([
            'myapp' => ['name' => 'My App'],
        ]);
        $builder = new UriBuilder($registry, $this->routeProvider);

        $result = $builder->withAppWebroot('myapp');
        self::assertSame('/myapp', $result->getPath());
    }

    #[Test]
    public function withAppWebrootThrowsForUnknownApp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->builder()->withAppWebroot('nonexistent');
    }

    // --- withAppWebroot with absolute URL configured ---

    #[Test]
    public function withAppWebrootAbsoluteUrlReplacesAuthority(): void
    {
        $registry = new RegistryState([
            'horde' => ['webroot' => 'https://dev.horde.org/horde'],
        ]);
        $requestUri = $this->createMock(UriInterface::class);
        $requestUri->method('getScheme')->willReturn('https');
        $requestUri->method('getAuthority')->willReturn('dev.horde.org');
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($requestUri);

        $builder = new UriBuilder($registry, $this->routeProvider, $request);
        $result = $builder->withAppWebroot('horde')->withPart('admin/config/config.php');

        self::assertSame('https', $result->getScheme());
        self::assertSame('dev.horde.org', $result->getHost());
        self::assertSame('/horde/admin/config/config.php', $result->getPath());
        self::assertSame(
            'https://dev.horde.org/horde/admin/config/config.php',
            (string) $result
        );
    }

    #[Test]
    public function withAppWebrootAbsoluteUrlWithDifferentHostOverridesRequestHost(): void
    {
        $registry = new RegistryState([
            'horde' => ['webroot' => 'https://assets.example.com/horde'],
        ]);
        $requestUri = $this->createMock(UriInterface::class);
        $requestUri->method('getScheme')->willReturn('https');
        $requestUri->method('getAuthority')->willReturn('dev.horde.org');
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($requestUri);

        $builder = new UriBuilder($registry, $this->routeProvider, $request);
        $result = $builder->withAppWebroot('horde')->withPart('static/foo.css');

        self::assertSame('assets.example.com', $result->getHost());
        self::assertSame('/horde/static/foo.css', $result->getPath());
    }

    #[Test]
    public function withAppWebrootAbsoluteUrlWithPort(): void
    {
        $registry = new RegistryState([
            'horde' => ['webroot' => 'https://horde.example.com:8443/horde'],
        ]);
        $builder = new UriBuilder($registry, $this->routeProvider);
        $result = $builder->withAppWebroot('horde');

        self::assertSame('https', $result->getScheme());
        self::assertSame('horde.example.com', $result->getHost());
        self::assertSame(8443, $result->getPort());
        self::assertSame('/horde', $result->getPath());
    }

    #[Test]
    public function withStaticUriAbsoluteWebrootProducesCleanUrl(): void
    {
        $registry = new RegistryState([
            'horde' => ['webroot' => 'https://assets.example.com/horde'],
        ]);
        $requestUri = $this->createMock(UriInterface::class);
        $requestUri->method('getScheme')->willReturn('https');
        $requestUri->method('getAuthority')->willReturn('app.example.com');
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($requestUri);

        $builder = new UriBuilder($registry, $this->routeProvider, $request);
        $result = $builder->withStaticUri();

        self::assertSame('assets.example.com', $result->getHost());
        self::assertSame('/horde/static', $result->getPath());
    }

    #[Test]
    public function withThemesUriAbsoluteWebrootProducesCleanUrl(): void
    {
        $registry = new RegistryState([
            'turba' => ['webroot' => 'https://assets.example.com/horde/turba'],
        ]);
        $builder = new UriBuilder($registry, $this->routeProvider);
        $result = $builder->withThemesUri('turba');

        self::assertSame('https', $result->getScheme());
        self::assertSame('assets.example.com', $result->getHost());
        self::assertSame('/horde/turba/themes', $result->getPath());
    }

    // --- withThemesUri ---

    #[Test]
    public function withThemesUriFromRegistry(): void
    {
        $result = $this->builder()->withThemesUri('kronolith');
        self::assertSame('/horde/kronolith/themes', $result->getPath());
    }

    #[Test]
    public function withThemesUriDefaultsFromWebroot(): void
    {
        $result = $this->builder()->withThemesUri('turba');
        self::assertSame('/horde/turba/themes', $result->getPath());
    }

    #[Test]
    public function withThemesUriThrowsForUnknownApp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->builder()->withThemesUri('nonexistent');
    }

    // --- withJsUri ---

    #[Test]
    public function withJsUriFromRegistry(): void
    {
        $result = $this->builder()->withJsUri('kronolith');
        self::assertSame('/horde/kronolith/js', $result->getPath());
    }

    #[Test]
    public function withJsUriDefaultsFromWebroot(): void
    {
        $result = $this->builder()->withJsUri('turba');
        self::assertSame('/horde/turba/js', $result->getPath());
    }

    // --- withStaticUri ---

    #[Test]
    public function withStaticUriFromRegistry(): void
    {
        $result = $this->builder()->withStaticUri();
        self::assertSame('/horde/static', $result->getPath());
    }

    #[Test]
    public function withStaticUriDefaultsFromHordeWebroot(): void
    {
        $registry = new RegistryState([
            'horde' => ['webroot' => '/custom'],
        ]);
        $builder = new UriBuilder($registry, $this->routeProvider);

        $result = $builder->withStaticUri();
        self::assertSame('/custom/static', $result->getPath());
    }

    // --- withSlug ---

    #[Test]
    public function withSlugAppendsWithTrailingSlash(): void
    {
        $result = $this->builder()
            ->withAppWebroot('kronolith')
            ->withSlug('events');

        self::assertSame('/horde/kronolith/events/', $result->getPath());
    }

    #[Test]
    public function withSlugTrimsInputSlashes(): void
    {
        $result = $this->builder()
            ->withPath('/base')
            ->withSlug('/segment/');

        self::assertSame('/base/segment/', $result->getPath());
    }

    #[Test]
    public function withSlugChaining(): void
    {
        $result = $this->builder()
            ->withAppWebroot('horde')
            ->withSlug('services')
            ->withSlug('portal');

        self::assertSame('/horde/services/portal/', $result->getPath());
    }

    // --- withPart ---

    #[Test]
    public function withPartAppendsWithoutTrailingSlash(): void
    {
        $result = $this->builder()
            ->withAppWebroot('kronolith')
            ->withPart('index.php');

        self::assertSame('/horde/kronolith/index.php', $result->getPath());
    }

    #[Test]
    public function withPartTrimsLeadingSlash(): void
    {
        $result = $this->builder()
            ->withPath('/base')
            ->withPart('/file.js');

        self::assertSame('/base/file.js', $result->getPath());
    }

    // --- Path normalization (double slashes) ---

    #[Test]
    public function pathNormalizesDoubleSlashes(): void
    {
        $result = $this->builder()->withPath('/foo//bar///baz');
        self::assertSame('/foo/bar/baz', $result->getPath());
    }

    // --- __toString ---

    #[Test]
    public function toStringProducesFullUri(): void
    {
        $result = $this->builder()
            ->withScheme('https')
            ->withHost('example.com')
            ->withAppWebroot('kronolith')
            ->withSlug('events')
            ->withPart('view.php')
            ->withQuery('id=42')
            ->withFragment('details');

        self::assertSame(
            'https://example.com/horde/kronolith/events/view.php?id=42#details',
            (string) $result
        );
    }

    #[Test]
    public function toStringPathOnly(): void
    {
        $result = $this->builder()->withAppWebroot('turba');
        self::assertSame('/horde/turba', (string) $result);
    }

    // --- toHordeUrl ---

    #[Test]
    public function toHordeUrlReturnsUrl(): void
    {
        $result = $this->builder()
            ->withScheme('https')
            ->withHost('example.com')
            ->withPath('/horde/turba')
            ->toHordeUrl();

        self::assertInstanceOf(Url::class, $result);
    }

    // --- Constructor with ServerRequest ---

    #[Test]
    public function constructorDerivesBaseFromRequest(): void
    {
        $requestUri = $this->createMock(UriInterface::class);
        $requestUri->method('getScheme')->willReturn('https');
        $requestUri->method('getAuthority')->willReturn('mail.example.com:8443');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($requestUri);

        $builder = new UriBuilder($this->registry, $this->routeProvider, $request);

        self::assertSame('https', $builder->getScheme());
        self::assertSame('mail.example.com', $builder->getHost());
        self::assertSame(8443, $builder->getPort());
    }

    #[Test]
    public function constructorExplicitUriOverridesRequest(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $builder = new UriBuilder(
            $this->registry,
            $this->routeProvider,
            $request,
            'http://other.example.com'
        );

        self::assertSame('other.example.com', $builder->getHost());
    }

    // --- withNamedRoute ---

    #[Test]
    public function withNamedRouteGeneratesPath(): void
    {
        $mapper = new Mapper();
        $mapper->explicit = true;
        $mapper->connect('event_show', '/events/:id', ['controller' => 'events', 'action' => 'show']);
        $mapper->createRegs();

        $routeProvider = new class ($mapper) implements RouteMapperProvider {
            public function __construct(private Mapper $mapper) {}
            public function getMapper(string $app): ?Mapper
            {
                return $app === 'kronolith' ? $this->mapper : null;
            }
        };

        $builder = new UriBuilder($this->registry, $routeProvider);
        $result = $builder->withNamedRoute('kronolith', 'event_show', ['id' => '42']);

        self::assertSame('/events/42', $result->getPath());
    }

    #[Test]
    public function withNamedRouteThrowsForUnknownApp(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No route mapper');
        $this->builder()->withNamedRoute('nonexistent', 'some_route');
    }

    #[Test]
    public function withNamedRouteThrowsForUnknownRoute(): void
    {
        $mapper = new Mapper();
        $mapper->explicit = true;
        $mapper->createRegs();

        $routeProvider = new class ($mapper) implements RouteMapperProvider {
            public function __construct(private Mapper $mapper) {}
            public function getMapper(string $app): ?Mapper
            {
                return $this->mapper;
            }
        };

        $builder = new UriBuilder($this->registry, $routeProvider);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown route');
        $builder->withNamedRoute('kronolith', 'no_such_route');
    }

    // --- Full fluent chain ---

    #[Test]
    public function fullFluentChain(): void
    {
        $mapper = new Mapper();
        $mapper->explicit = true;
        $mapper->connect('task_complete', '/task/:id/complete', [
            'controller' => 'tasks',
            'action' => 'complete',
        ]);
        $mapper->createRegs();

        $routeProvider = new class ($mapper) implements RouteMapperProvider {
            public function __construct(private Mapper $mapper) {}
            public function getMapper(string $app): ?Mapper
            {
                return $this->mapper;
            }
        };

        $builder = new UriBuilder($this->registry, $routeProvider);
        $result = $builder
            ->withScheme('https')
            ->withHost('tasks.example.com')
            ->withNamedRoute('kronolith', 'task_complete', ['id' => '7'])
            ->withQuery('confirm=1')
            ->withFragment('top');

        self::assertSame(
            'https://tasks.example.com/task/7/complete?confirm=1#top',
            (string) $result
        );
        self::assertInstanceOf(UriBuilder::class, $result);
    }
}
