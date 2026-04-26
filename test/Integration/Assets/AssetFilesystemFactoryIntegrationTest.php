<?php

declare(strict_types=1);

namespace Horde\Core\Test\Integration\Assets;

use Horde\Core\Assets\AssetFilesystem;
use Horde\Core\Assets\LocalAssetFilesystem;
use Horde\Core\Assets\ResponsiveAssetsFilesystem;
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class AssetFilesystemFactoryIntegrationTest extends TestCase
{
    private Injector $injector;

    protected function setUp(): void
    {
        $this->injector = new Injector(new TopLevel());
    }

    #[Test]
    public function injectorResolvesAssetFilesystem(): void
    {
        $fs = $this->injector->getInstance(AssetFilesystem::class);

        self::assertInstanceOf(AssetFilesystem::class, $fs);
        self::assertInstanceOf(LocalAssetFilesystem::class, $fs);
    }

    #[Test]
    public function factoryProducedFilesystemChecksFiles(): void
    {
        $fs = $this->injector->getInstance(AssetFilesystem::class);

        self::assertTrue($fs->fileExists(__FILE__));
        self::assertFalse($fs->fileExists('/tmp/nonexistent-integration-' . uniqid() . '.php'));
    }

    #[Test]
    public function localAssetFilesystemAlsoServesAsResponsiveAssetsFilesystem(): void
    {
        $fs = $this->injector->getInstance(AssetFilesystem::class);

        self::assertInstanceOf(ResponsiveAssetsFilesystem::class, $fs);
    }

    #[Test]
    public function injectorReturnsSameInstance(): void
    {
        $first = $this->injector->getInstance(AssetFilesystem::class);
        $second = $this->injector->getInstance(AssetFilesystem::class);

        self::assertSame($first, $second);
    }
}
