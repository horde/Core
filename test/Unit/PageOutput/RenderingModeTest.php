<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\PageOutput;

use Horde\Core\PageOutput\RenderingMode;
use Horde\Core\PageOutput\ViewMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenderingMode::class)]
class RenderingModeTest extends TestCase
{
    #[Test]
    public function basicIsDesktop(): void
    {
        self::assertTrue(RenderingMode::BASIC->isDesktop());
    }

    #[Test]
    public function dynamicIsDesktop(): void
    {
        self::assertTrue(RenderingMode::DYNAMIC->isDesktop());
    }

    #[Test]
    public function responsiveIsNotDesktop(): void
    {
        self::assertFalse(RenderingMode::RESPONSIVE->isDesktop());
    }

    #[Test]
    public function basicToViewModeReturnsBasic(): void
    {
        self::assertSame(ViewMode::BASIC, RenderingMode::BASIC->toViewMode());
    }

    #[Test]
    public function dynamicToViewModeReturnsDynamic(): void
    {
        self::assertSame(ViewMode::DYNAMIC, RenderingMode::DYNAMIC->toViewMode());
    }

    #[Test]
    public function responsiveToViewModeReturnsBasic(): void
    {
        self::assertSame(ViewMode::BASIC, RenderingMode::RESPONSIVE->toViewMode());
    }

    #[Test]
    public function enumBackedValues(): void
    {
        self::assertSame('basic', RenderingMode::BASIC->value);
        self::assertSame('dynamic', RenderingMode::DYNAMIC->value);
        self::assertSame('responsive', RenderingMode::RESPONSIVE->value);
    }

    #[Test]
    public function tryFromValidValues(): void
    {
        self::assertSame(RenderingMode::BASIC, RenderingMode::tryFrom('basic'));
        self::assertSame(RenderingMode::DYNAMIC, RenderingMode::tryFrom('dynamic'));
        self::assertSame(RenderingMode::RESPONSIVE, RenderingMode::tryFrom('responsive'));
    }

    #[Test]
    public function tryFromInvalidReturnsNull(): void
    {
        self::assertNull(RenderingMode::tryFrom('invalid'));
    }
}
