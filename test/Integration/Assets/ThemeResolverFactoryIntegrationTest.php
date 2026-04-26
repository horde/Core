<?php

declare(strict_types=1);

namespace Horde\Core\Test\Integration\Assets;

use Horde\Core\Assets\PrefsThemeResolver;
use Horde\Core\Assets\ThemeResolver;
use Horde\Core\Service\PrefsService;
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class ThemeResolverFactoryIntegrationTest extends TestCase
{
    private Injector $injector;

    protected function setUp(): void
    {
        $this->injector = new Injector(new TopLevel());

        $prefsService = $this->createMock(PrefsService::class);
        $prefsService->method('getValue')->willReturn(null);
        $this->injector->setInstance(PrefsService::class, $prefsService);
    }

    #[Test]
    public function injectorResolvesThemeResolver(): void
    {
        $resolver = $this->injector->getInstance(ThemeResolver::class);

        self::assertInstanceOf(ThemeResolver::class, $resolver);
        self::assertInstanceOf(PrefsThemeResolver::class, $resolver);
    }

    #[Test]
    public function factoryProducedResolverReturnsDefault(): void
    {
        $resolver = $this->injector->getInstance(ThemeResolver::class);

        self::assertSame('default', $resolver->resolve('unknown-user'));
    }

    #[Test]
    public function injectorReturnsSameInstance(): void
    {
        $first = $this->injector->getInstance(ThemeResolver::class);
        $second = $this->injector->getInstance(ThemeResolver::class);

        self::assertSame($first, $second);
    }
}
