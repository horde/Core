<?php

declare(strict_types=1);

namespace Horde\Core\Test\Integration\Path;

use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Config\RegistryState;
use Horde\Core\Path\PathBuilder;
use Horde\Core\Path\PathBuilderInterface;
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class PathBuilderFactoryIntegrationTest extends TestCase
{
    private Injector $injector;

    protected function setUp(): void
    {
        $this->injector = new Injector(new TopLevel());

        $registryState = new RegistryState([
            'horde' => [
                'fileroot' => '/srv/www/horde',
                'staticfs' => '/srv/www/horde/static',
            ],
            'turba' => [
                'fileroot' => '/srv/www/horde/turba',
            ],
        ]);

        $loader = $this->createMock(RegistryConfigLoader::class);
        $loader->method('load')->willReturn($registryState);
        $this->injector->setInstance(RegistryConfigLoader::class, $loader);
    }

    #[Test]
    public function injectorResolvesPathBuilderInterface(): void
    {
        $builder = $this->injector->getInstance(PathBuilderInterface::class);

        self::assertInstanceOf(PathBuilderInterface::class, $builder);
        self::assertInstanceOf(PathBuilder::class, $builder);
    }

    #[Test]
    public function factoryProducedBuilderResolvesRegistry(): void
    {
        $builder = $this->injector->getInstance(PathBuilderInterface::class);
        $result = $builder->withAppFileroot('turba');

        self::assertSame('/srv/www/horde/turba', (string) $result);
    }

    #[Test]
    public function factoryProducedBuilderSupportsChaining(): void
    {
        $builder = $this->injector->getInstance(PathBuilderInterface::class);
        $result = $builder
            ->withAppFileroot('turba')
            ->withSlug('config')
            ->withPart('conf.php');

        self::assertInstanceOf(PathBuilder::class, $result);
        self::assertSame('/srv/www/horde/turba/config/conf.php', (string) $result);
    }

    #[Test]
    public function injectorReturnsSameInstance(): void
    {
        $first = $this->injector->getInstance(PathBuilderInterface::class);
        $second = $this->injector->getInstance(PathBuilderInterface::class);

        self::assertSame($first, $second);
    }
}
