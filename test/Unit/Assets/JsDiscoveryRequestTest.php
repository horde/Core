<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\Assets;

use Horde\Core\Assets\JsDiscoveryRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsDiscoveryRequest::class)]
class JsDiscoveryRequestTest extends TestCase
{
    #[Test]
    public function defaults(): void
    {
        $request = new JsDiscoveryRequest();

        self::assertSame([], $request->files);
        self::assertSame('horde', $request->app);
        self::assertSame('default', $request->theme);
    }

    #[Test]
    public function customValues(): void
    {
        $request = new JsDiscoveryRequest(
            files: ['theme.js'],
            app: 'turba',
            theme: 'silver',
        );

        self::assertSame(['theme.js'], $request->files);
        self::assertSame('turba', $request->app);
        self::assertSame('silver', $request->theme);
    }
}
