<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\JsAssetEntry;
use Horde\Core\Assets\JsDiscoveryResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsDiscoveryResult::class)]
#[CoversClass(JsAssetEntry::class)]
class JsDiscoveryResultTest extends TestCase
{
    #[Test]
    public function emptyResult(): void
    {
        $result = new JsDiscoveryResult([], 'default', 'horde');

        self::assertTrue($result->isEmpty());
        self::assertCount(0, $result);
        self::assertSame([], $result->toArray());
        self::assertSame('default', $result->getTheme());
        self::assertSame('horde', $result->getApp());
    }

    #[Test]
    public function iteratesEntriesInOrder(): void
    {
        $a = new JsAssetEntry('/fs/a.js', '/uri/a.js', 'horde');
        $b = new JsAssetEntry('/fs/b.js', '/uri/b.js', 'turba');
        $result = new JsDiscoveryResult([$a, $b], 'silver', 'turba');

        self::assertFalse($result->isEmpty());
        self::assertCount(2, $result);

        $collected = [];
        foreach ($result as $entry) {
            $collected[] = $entry->uri;
        }

        self::assertSame(['/uri/a.js', '/uri/b.js'], $collected);
        self::assertSame([$a, $b], $result->toArray());
    }

    #[Test]
    public function entryExposesFields(): void
    {
        $entry = new JsAssetEntry('/fs/theme.js', '/uri/theme.js', 'turba');

        self::assertSame('/fs/theme.js', $entry->fsPath);
        self::assertSame('/uri/theme.js', $entry->uri);
        self::assertSame('turba', $entry->app);
    }
}
