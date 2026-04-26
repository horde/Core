<?php

declare(strict_types=1);

namespace Horde\Core\Test\Integration\Uri;

use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Config\RegistryState;
use Horde\Core\Uri\RouteMapperProvider;
use Horde\Core\Uri\UriBuilder;
use Horde\Core\Uri\UriBuilderInterface;
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use Horde\Routes\Mapper;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class UriBuilderFactoryIntegrationTest extends TestCase
{
    private Injector $injector;

    protected function setUp(): void
    {
        $this->injector = new Injector(new TopLevel());

        $registryState = new RegistryState([
            'horde' => [
                'webroot' => '/horde',
                'staticuri' => '/horde/static',
            ],
            'kronolith' => [
                'webroot' => '/horde/kronolith',
            ],
        ]);

        $loader = $this->createMock(RegistryConfigLoader::class);
        $loader->method('load')->willReturn($registryState);
        $this->injector->setInstance(RegistryConfigLoader::class, $loader);

        $routeProvider = new class implements RouteMapperProvider {
            public function getMapper(string $app): ?Mapper
            {
                return null;
            }
        };
        $this->injector->setInstance(RouteMapperProvider::class, $routeProvider);
    }

    #[Test]
    public function injectorResolvesUriBuilderInterface(): void
    {
        $builder = $this->injector->getInstance(UriBuilderInterface::class);

        self::assertInstanceOf(UriBuilderInterface::class, $builder);
        self::assertInstanceOf(UriBuilder::class, $builder);
    }

    #[Test]
    public function factoryProducedBuilderResolvesRegistry(): void
    {
        $builder = $this->injector->getInstance(UriBuilderInterface::class);
        $result = $builder->withAppWebroot('kronolith');

        self::assertSame('/horde/kronolith', $result->getPath());
    }

    #[Test]
    public function factoryProducedBuilderSupportsPsr7Chain(): void
    {
        $builder = $this->injector->getInstance(UriBuilderInterface::class);
        $result = $builder
            ->withScheme('https')
            ->withHost('example.com')
            ->withAppWebroot('horde')
            ->withSlug('services')
            ->withPart('portal.php');

        self::assertInstanceOf(UriBuilder::class, $result);
        self::assertSame(
            'https://example.com/horde/services/portal.php',
            (string) $result
        );
    }

    #[Test]
    public function injectorReturnsSameInstance(): void
    {
        $first = $this->injector->getInstance(UriBuilderInterface::class);
        $second = $this->injector->getInstance(UriBuilderInterface::class);

        self::assertSame($first, $second);
    }
}
