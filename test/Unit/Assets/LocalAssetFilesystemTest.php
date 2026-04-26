<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\AssetFilesystem;
use Horde\Core\Assets\LocalAssetFilesystem;
use Horde\Core\Assets\ResponsiveAssetsFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalAssetFilesystem::class)]
class LocalAssetFilesystemTest extends TestCase
{
    private LocalAssetFilesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new LocalAssetFilesystem();
    }

    #[Test]
    public function implementsAssetFilesystem(): void
    {
        self::assertInstanceOf(AssetFilesystem::class, $this->fs);
    }

    #[Test]
    public function implementsResponsiveAssetsFilesystem(): void
    {
        self::assertInstanceOf(ResponsiveAssetsFilesystem::class, $this->fs);
    }

    #[Test]
    public function existingFileReturnsTrue(): void
    {
        self::assertTrue($this->fs->fileExists(__FILE__));
    }

    #[Test]
    public function nonExistingFileReturnsFalse(): void
    {
        self::assertFalse($this->fs->fileExists('/tmp/nonexistent-' . uniqid() . '.php'));
    }

    #[Test]
    public function directoryReturnsFalseForFileExists(): void
    {
        self::assertFalse($this->fs->fileExists(__DIR__));
    }

    #[Test]
    public function readableFileReturnsTrue(): void
    {
        self::assertTrue($this->fs->isReadable(__FILE__));
    }

    #[Test]
    public function nonExistingFileIsNotReadable(): void
    {
        self::assertFalse($this->fs->isReadable('/tmp/nonexistent-' . uniqid() . '.php'));
    }
}
