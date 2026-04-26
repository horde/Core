<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\CssDiscoveryRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CssDiscoveryRequest::class)]
class CssDiscoveryRequestTest extends TestCase
{
    #[Test]
    public function defaults(): void
    {
        $request = new CssDiscoveryRequest();

        self::assertSame(['screen.css'], $request->files);
        self::assertSame('horde', $request->app);
        self::assertSame('default', $request->theme);
        self::assertNull($request->subView);
    }

    #[Test]
    public function customValues(): void
    {
        $request = new CssDiscoveryRequest(
            files: ['responsive.css'],
            app: 'turba',
            theme: 'silver',
            subView: 'dynamic',
        );

        self::assertSame(['responsive.css'], $request->files);
        self::assertSame('turba', $request->app);
        self::assertSame('silver', $request->theme);
        self::assertSame('dynamic', $request->subView);
    }

    #[Test]
    public function multipleFiles(): void
    {
        $request = new CssDiscoveryRequest(files: ['screen.css', 'print.css']);

        self::assertSame(['screen.css', 'print.css'], $request->files);
    }
}
