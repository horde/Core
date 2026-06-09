<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Factory;

use Horde\Compress\CompressFactory;
use Horde\Compress\CompressorInterface;
use Horde_Compress_Tar;
use Horde_Compress_Zip;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Tests that CompressFactory produces objects compatible with what
 * Horde_Core_Factory_MimeViewer passes to viewer constructors.
 *
 * The factory creates compress instances via CompressFactory::create()
 * and passes them to viewers as config params. This test verifies the
 * created instances can decompress data in the expected format.
 */
#[CoversNothing]
class MimeViewerCompressTest extends TestCase
{
    /**
     * CompressFactory::create('zip') returns an object whose decompress()
     * with ZIP_LIST returns file info array — same contract as Horde_Compress_Zip.
     */
    public function testZipFactoryProducesWorkingInstance(): void
    {
        $factory = new CompressFactory();
        $zip = $factory->create('zip');

        $this->assertInstanceOf(CompressorInterface::class, $zip);

        $legacyZip = new Horde_Compress_Zip();
        $archive = $legacyZip->compress([
            ['data' => 'test content', 'name' => 'test.txt'],
        ]);

        $list = $zip->decompress($archive, ['action' => \Horde\Compress\Driver\Zip::ZIP_LIST]);
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $this->assertEquals('test.txt', $list[0]['name']);
    }

    /**
     * CompressFactory::create('tar') returns an object whose decompress()
     * returns file entries — same contract as Horde_Compress_Tar.
     */
    public function testTarFactoryProducesWorkingInstance(): void
    {
        $factory = new CompressFactory();
        $tar = $factory->create('tar');

        $this->assertInstanceOf(CompressorInterface::class, $tar);

        $legacyTar = new Horde_Compress_Tar();
        $archive = $legacyTar->compress([
            ['data' => 'tar test', 'name' => 'file.txt'],
        ]);

        $entries = $tar->decompress($archive);
        $this->assertIsArray($entries);
        $this->assertCount(1, $entries);
        $this->assertEquals('file.txt', $entries[0]['name']);
    }

    /**
     * CompressFactory::create('tnef') returns a working instance.
     */
    public function testTnefFactoryProducesWorkingInstance(): void
    {
        $factory = new CompressFactory();
        $tnef = $factory->create('tnef');

        $this->assertInstanceOf(CompressorInterface::class, $tnef);
    }

    /**
     * CompressFactory::create('rar') returns a working instance.
     */
    public function testRarFactoryProducesWorkingInstance(): void
    {
        $factory = new CompressFactory();
        $rar = $factory->create('rar');

        $this->assertInstanceOf(CompressorInterface::class, $rar);
    }

    /**
     * CompressFactory::create('gzip') returns a working instance.
     */
    public function testGzipFactoryProducesWorkingInstance(): void
    {
        $factory = new CompressFactory();
        $gzip = $factory->create('gzip');

        $this->assertInstanceOf(CompressorInterface::class, $gzip);
    }
}
