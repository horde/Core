<?php

declare(strict_types=1);

namespace Horde\Core\Test\Integration\Assets;

use Horde\Core\Assets\AssetFilesystem;
use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\Assets\LocalAssetFilesystem;
use Horde\Core\Assets\PathBasedJsDiscoverer;
use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Config\RegistryState;
use Horde\Core\Uri\RouteMapperProvider;
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use Horde\Routes\Mapper;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class JsDiscovererFactoryIntegrationTest extends TestCase
{
    private Injector $injector;

    protected function setUp(): void
    {
        $this->injector = new Injector(new TopLevel());

        $registryState = new RegistryState([
            'horde' => [
                'fileroot' => '/srv/www/horde',
                'webroot' => '/horde',
            ],
        ]);

        $loader = $this->createMock(RegistryConfigLoader::class);
        $loader->method('load')->willReturn($registryState);
        $this->injector->setInstance(RegistryConfigLoader::class, $loader);
        $this->injector->setInstance(AssetFilesystem::class, new LocalAssetFilesystem());

        $routeProvider = new class implements RouteMapperProvider {
            public function getMapper(string $app): ?Mapper
            {
                return null;
            }
        };
        $this->injector->setInstance(RouteMapperProvider::class, $routeProvider);
    }

    #[Test]
    public function injectorResolvesJsDiscoverer(): void
    {
        $discoverer = $this->injector->getInstance(JsDiscoverer::class);

        self::assertInstanceOf(JsDiscoverer::class, $discoverer);
        self::assertInstanceOf(PathBasedJsDiscoverer::class, $discoverer);
    }

    #[Test]
    public function injectorReturnsSameInstance(): void
    {
        $first = $this->injector->getInstance(JsDiscoverer::class);
        $second = $this->injector->getInstance(JsDiscoverer::class);

        self::assertSame($first, $second);
    }
}
