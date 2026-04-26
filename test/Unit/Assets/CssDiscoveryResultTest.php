<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\CssAssetEntry;
use Horde\Core\Assets\CssDiscoveryResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CssDiscoveryResult::class)]
class CssDiscoveryResultTest extends TestCase
{
    #[Test]
    public function emptyResult(): void
    {
        $result = new CssDiscoveryResult([], 'default', 'horde');

        self::assertTrue($result->isEmpty());
        self::assertCount(0, $result);
        self::assertSame([], $result->toArray());
    }

    #[Test]
    public function metadataAccessors(): void
    {
        $result = new CssDiscoveryResult([], 'silver', 'turba');

        self::assertSame('silver', $result->getTheme());
        self::assertSame('turba', $result->getApp());
    }

    #[Test]
    public function iterationYieldsEntries(): void
    {
        $entries = [
            new CssAssetEntry('/fs/a.css', '/uri/a.css', 'horde'),
            new CssAssetEntry('/fs/b.css', '/uri/b.css', 'turba'),
        ];
        $result = new CssDiscoveryResult($entries, 'default', 'horde');

        self::assertFalse($result->isEmpty());
        self::assertCount(2, $result);

        $collected = [];
        foreach ($result as $entry) {
            $collected[] = $entry;
        }

        self::assertSame($entries, $collected);
    }

    #[Test]
    public function toArrayReturnsEntries(): void
    {
        $entries = [
            new CssAssetEntry('/fs/x.css', '/uri/x.css'),
        ];
        $result = new CssDiscoveryResult($entries, 'default', 'horde');

        self::assertSame($entries, $result->toArray());
    }

    #[Test]
    public function countMatchesEntries(): void
    {
        $entries = [
            new CssAssetEntry('/a', '/a'),
            new CssAssetEntry('/b', '/b'),
            new CssAssetEntry('/c', '/c'),
        ];
        $result = new CssDiscoveryResult($entries, 'default', 'horde');

        self::assertSame(3, $result->count());
    }
}
