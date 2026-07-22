<?php

declare(strict_types=1);

namespace Horde\Core\Test\Unit\PageOutput;

use Horde\Browser\Browser;
use Horde\Core\PageOutput\RenderingMode;
use Horde\Core\PageOutput\RenderingModeResolver;
use Horde\Core\Session\SessionAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenderingModeResolver::class)]
class RenderingModeResolverTest extends TestCase
{
    /**
     * For tests that exercise only one of (session, browser), build a stub
     * for the unused dependency. The resolver never reads it on the path
     * under test, so a pure type-hint stub is the right shape.
     */
    private function unusedSessionStub(): SessionAccess
    {
        return $this->createStub(SessionAccess::class);
    }

    private function unusedBrowserStub(): Browser
    {
        return $this->createStub(Browser::class);
    }

    #[Test]
    public function authenticatedUserWithStoredModeUsesSessionValue(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())->method('getAuthId')->willReturn('testuser');
        $session->expects(self::once())->method('hasScoped')
            ->with('horde', 'rendering_mode')->willReturn(true);
        $session->expects(self::once())->method('getScoped')
            ->with('horde', 'rendering_mode')->willReturn('responsive');

        $resolver = new RenderingModeResolver($session, $this->unusedBrowserStub());
        self::assertSame(RenderingMode::RESPONSIVE, $resolver->resolve());
    }

    #[Test]
    public function authenticatedUserWithDynamicModeStored(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())->method('getAuthId')->willReturn('testuser');
        $session->expects(self::once())->method('hasScoped')
            ->with('horde', 'rendering_mode')->willReturn(true);
        $session->expects(self::once())->method('getScoped')
            ->with('horde', 'rendering_mode')->willReturn('dynamic');

        $resolver = new RenderingModeResolver($session, $this->unusedBrowserStub());
        self::assertSame(RenderingMode::DYNAMIC, $resolver->resolve());
    }

    #[Test]
    public function authenticatedUserWithInvalidStoredModeFallsToBrowserDetection(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())->method('getAuthId')->willReturn('testuser');
        $session->expects(self::once())->method('hasScoped')
            ->with('horde', 'rendering_mode')->willReturn(true);
        $session->expects(self::once())->method('getScoped')
            ->with('horde', 'rendering_mode')->willReturn('invalid_mode');

        $browser = $this->createMock(Browser::class);
        $browser->expects(self::once())->method('mobile')->willReturn(false);
        $browser->expects(self::once())->method('tablet')->willReturn(false);
        $browser->expects(self::once())->method('hasFeature')
            ->with('ajax')->willReturn(true);

        $resolver = new RenderingModeResolver($session, $browser);
        self::assertSame(RenderingMode::DYNAMIC, $resolver->resolve());
    }

    #[Test]
    public function authenticatedUserWithNoStoredModeFallsToBrowserDetection(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())->method('getAuthId')->willReturn('testuser');
        $session->expects(self::once())->method('hasScoped')
            ->with('horde', 'rendering_mode')->willReturn(false);
        $session->expects(self::never())->method('getScoped');

        $browser = $this->createMock(Browser::class);
        $browser->expects(self::once())->method('mobile')->willReturn(true);
        // tablet() and hasFeature() are short-circuited by mobile() returning true.
        $browser->expects(self::never())->method('tablet');
        $browser->expects(self::never())->method('hasFeature');

        $resolver = new RenderingModeResolver($session, $browser);
        self::assertSame(RenderingMode::RESPONSIVE, $resolver->resolve());
    }

    #[Test]
    public function anonymousUserOnMobileBrowserGetsResponsive(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())->method('getAuthId')->willReturn(null);
        $session->expects(self::never())->method('hasScoped');

        $browser = $this->createMock(Browser::class);
        $browser->expects(self::once())->method('mobile')->willReturn(true);
        $browser->expects(self::never())->method('tablet');

        $resolver = new RenderingModeResolver($session, $browser);
        self::assertSame(RenderingMode::RESPONSIVE, $resolver->resolve());
    }

    #[Test]
    public function anonymousUserOnTabletGetsResponsive(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())->method('getAuthId')->willReturn(null);

        $browser = $this->createMock(Browser::class);
        $browser->expects(self::once())->method('mobile')->willReturn(false);
        $browser->expects(self::once())->method('tablet')->willReturn(true);
        $browser->expects(self::never())->method('hasFeature');

        $resolver = new RenderingModeResolver($session, $browser);
        self::assertSame(RenderingMode::RESPONSIVE, $resolver->resolve());
    }

    #[Test]
    public function anonymousUserOnDesktopWithAjaxGetsDynamic(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())->method('getAuthId')->willReturn(null);

        $browser = $this->createMock(Browser::class);
        $browser->expects(self::once())->method('mobile')->willReturn(false);
        $browser->expects(self::once())->method('tablet')->willReturn(false);
        $browser->expects(self::once())->method('hasFeature')
            ->with('ajax')->willReturn(true);

        $resolver = new RenderingModeResolver($session, $browser);
        self::assertSame(RenderingMode::DYNAMIC, $resolver->resolve());
    }

    #[Test]
    public function anonymousUserOnDesktopWithoutAjaxGetsBasic(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())->method('getAuthId')->willReturn(null);

        $browser = $this->createMock(Browser::class);
        $browser->expects(self::once())->method('mobile')->willReturn(false);
        $browser->expects(self::once())->method('tablet')->willReturn(false);
        $browser->expects(self::once())->method('hasFeature')
            ->with('ajax')->willReturn(false);

        $resolver = new RenderingModeResolver($session, $browser);
        self::assertSame(RenderingMode::BASIC, $resolver->resolve());
    }

    #[Test]
    public function storeModeWritesToSession(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())
            ->method('setScoped')
            ->with('horde', 'rendering_mode', 'responsive');
        $session->expects(self::never())->method('removeScoped');

        $resolver = new RenderingModeResolver($session, $this->unusedBrowserStub());
        $resolver->storeMode(RenderingMode::RESPONSIVE);
    }

    #[Test]
    public function storeModeNullRemovesFromSession(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())->method('hasScoped')
            ->with('horde', 'rendering_mode')->willReturn(true);
        $session->expects(self::once())
            ->method('removeScoped')
            ->with('horde', 'rendering_mode');
        $session->expects(self::never())->method('setScoped');

        $resolver = new RenderingModeResolver($session, $this->unusedBrowserStub());
        $resolver->storeMode(null);
    }

    #[Test]
    public function storeModeNullWithNoExistingValueDoesNothing(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects(self::once())->method('hasScoped')
            ->with('horde', 'rendering_mode')->willReturn(false);
        $session->expects(self::never())->method('removeScoped');
        $session->expects(self::never())->method('setScoped');

        $resolver = new RenderingModeResolver($session, $this->unusedBrowserStub());
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
