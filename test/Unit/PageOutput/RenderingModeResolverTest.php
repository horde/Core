<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\PageOutput;

use Horde\Browser\Browser;
use Horde\Core\PageOutput\RenderingMode;
use Horde\Core\PageOutput\RenderingModeResolver;
use Horde\Core\Session\HordeSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenderingModeResolver::class)]
class RenderingModeResolverTest extends TestCase
{
    private function createResolver(
        ?HordeSession $session = null,
        ?Browser $browser = null,
    ): RenderingModeResolver {
        $session ??= $this->createMock(HordeSession::class);
        $browser ??= $this->createMock(Browser::class);

        return new RenderingModeResolver($session, $browser);
    }

    #[Test]
    public function authenticatedUserWithStoredModeUsesSessionValue(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');
        $session->method('hasScoped')->with('horde', 'rendering_mode')->willReturn(true);
        $session->method('getScoped')->with('horde', 'rendering_mode')->willReturn('responsive');

        $resolver = $this->createResolver(session: $session);
        self::assertSame(RenderingMode::RESPONSIVE, $resolver->resolve());
    }

    #[Test]
    public function authenticatedUserWithDynamicModeStored(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');
        $session->method('hasScoped')->with('horde', 'rendering_mode')->willReturn(true);
        $session->method('getScoped')->with('horde', 'rendering_mode')->willReturn('dynamic');

        $resolver = $this->createResolver(session: $session);
        self::assertSame(RenderingMode::DYNAMIC, $resolver->resolve());
    }

    #[Test]
    public function authenticatedUserWithInvalidStoredModeFallsToBrowserDetection(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');
        $session->method('hasScoped')->with('horde', 'rendering_mode')->willReturn(true);
        $session->method('getScoped')->with('horde', 'rendering_mode')->willReturn('invalid_mode');

        $browser = $this->createMock(Browser::class);
        $browser->method('mobile')->willReturn(false);
        $browser->method('tablet')->willReturn(false);
        $browser->method('hasFeature')->with('ajax')->willReturn(true);

        $resolver = $this->createResolver(session: $session, browser: $browser);
        self::assertSame(RenderingMode::DYNAMIC, $resolver->resolve());
    }

    #[Test]
    public function authenticatedUserWithNoStoredModeFallsToBrowserDetection(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');
        $session->method('hasScoped')->with('horde', 'rendering_mode')->willReturn(false);

        $browser = $this->createMock(Browser::class);
        $browser->method('mobile')->willReturn(true);
        $browser->method('tablet')->willReturn(false);

        $resolver = $this->createResolver(session: $session, browser: $browser);
        self::assertSame(RenderingMode::RESPONSIVE, $resolver->resolve());
    }

    #[Test]
    public function anonymousUserOnMobileBrowserGetsResponsive(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $browser = $this->createMock(Browser::class);
        $browser->method('mobile')->willReturn(true);
        $browser->method('tablet')->willReturn(false);

        $resolver = $this->createResolver(session: $session, browser: $browser);
        self::assertSame(RenderingMode::RESPONSIVE, $resolver->resolve());
    }

    #[Test]
    public function anonymousUserOnTabletGetsResponsive(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $browser = $this->createMock(Browser::class);
        $browser->method('mobile')->willReturn(false);
        $browser->method('tablet')->willReturn(true);

        $resolver = $this->createResolver(session: $session, browser: $browser);
        self::assertSame(RenderingMode::RESPONSIVE, $resolver->resolve());
    }

    #[Test]
    public function anonymousUserOnDesktopWithAjaxGetsDynamic(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $browser = $this->createMock(Browser::class);
        $browser->method('mobile')->willReturn(false);
        $browser->method('tablet')->willReturn(false);
        $browser->method('hasFeature')->with('ajax')->willReturn(true);

        $resolver = $this->createResolver(session: $session, browser: $browser);
        self::assertSame(RenderingMode::DYNAMIC, $resolver->resolve());
    }

    #[Test]
    public function anonymousUserOnDesktopWithoutAjaxGetsBasic(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $browser = $this->createMock(Browser::class);
        $browser->method('mobile')->willReturn(false);
        $browser->method('tablet')->willReturn(false);
        $browser->method('hasFeature')->with('ajax')->willReturn(false);

        $resolver = $this->createResolver(session: $session, browser: $browser);
        self::assertSame(RenderingMode::BASIC, $resolver->resolve());
    }

    #[Test]
    public function storeModeWritesToSession(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->expects(self::once())
            ->method('setScoped')
            ->with('horde', 'rendering_mode', 'responsive');

        $resolver = $this->createResolver(session: $session);
        $resolver->storeMode(RenderingMode::RESPONSIVE);
    }

    #[Test]
    public function storeModeNullRemovesFromSession(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->method('hasScoped')->with('horde', 'rendering_mode')->willReturn(true);
        $session->expects(self::once())
            ->method('removeScoped')
            ->with('horde', 'rendering_mode');

        $resolver = $this->createResolver(session: $session);
        $resolver->storeMode(null);
    }

    #[Test]
    public function storeModeNullWithNoExistingValueDoesNothing(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->method('hasScoped')->with('horde', 'rendering_mode')->willReturn(false);
        $session->expects(self::never())->method('removeScoped');

        $resolver = $this->createResolver(session: $session);
        $resolver->storeMode(null);
    }

    #[Test]
    public function fromLegacyViewNameMapsDynamic(): void
    {
        self::assertSame(RenderingMode::DYNAMIC, RenderingModeResolver::fromLegacyViewName('dynamic'));
    }

    #[Test]
    public function fromLegacyViewNameMapsBasic(): void
    {
        self::assertSame(RenderingMode::BASIC, RenderingModeResolver::fromLegacyViewName('basic'));
    }

    #[Test]
    public function fromLegacyViewNameMapsSmartmobileToResponsive(): void
    {
        self::assertSame(RenderingMode::RESPONSIVE, RenderingModeResolver::fromLegacyViewName('smartmobile'));
    }

    #[Test]
    public function fromLegacyViewNameMapsMobileToResponsive(): void
    {
        self::assertSame(RenderingMode::RESPONSIVE, RenderingModeResolver::fromLegacyViewName('mobile'));
    }

    #[Test]
    public function fromLegacyViewNameReturnsNullForAuto(): void
    {
        self::assertNull(RenderingModeResolver::fromLegacyViewName('auto'));
    }

    #[Test]
    public function fromLegacyViewNameReturnsNullForUnknown(): void
    {
        self::assertNull(RenderingModeResolver::fromLegacyViewName('something_else'));
    }
}
