<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Session;

use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionAccess;
use Horde\Core\Session\SessionAccessor;
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use Horde_Core_Secret_Cbc;
use Horde_Session_Null;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for horde/Core#207.
 *
 * `session_control = 'none'` boots consumers (rpc.php → ActiveSync,
 * WebDAV, SyncML, EAS Autodiscover; CLI tools via appInit()) through
 * {@see Horde_Session_Null}. The refactor in horde/Core#203 moved
 * bootstrap consumers (Nlsconfig, prefs cache, notification handler)
 * onto {@see SessionAccess}, whose accessor throws until something
 * calls {@see SessionAccessor::replaceWith()}. Before this fix,
 * `Horde_Session_Null::_start()` never touched the accessor, and the
 * very next line in `Horde_Registry::__construct()` — the
 * `setLanguageEnvironment()` call — fataled on
 * `SessionAccessor::current()`.
 *
 * The fix moves the injector-plus-accessor publish into a shared
 * trait ({@see PublishesModernSessionToAccessorTrait}) and calls it
 * from `Horde_Session_Null::_start()`. These tests pin that
 * contract.
 */
#[CoversClass(Horde_Session_Null::class)]
class HordeSessionNullTest extends TestCase
{
    private ?Injector $previousInjector = null;

    /** @var array<mixed>|null */
    private ?array $previousSession = null;

    protected function setUp(): void
    {
        // Preserve any injector and $_SESSION contents the surrounding
        // suite may have wired so we can restore them after the test.
        // Horde_Session_Null::__construct() and _start() mutate the
        // global $_SESSION via a live reference; without restoration
        // sibling tests (HordeSessionShimBeginTest) that assume a
        // pristine `$_SESSION[BEGIN]` slot fail.
        $this->previousInjector = $GLOBALS['injector'] ?? null;
        $this->previousSession = $_SESSION ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->previousInjector === null) {
            unset($GLOBALS['injector']);
        } else {
            $GLOBALS['injector'] = $this->previousInjector;
        }
        if ($this->previousSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->previousSession;
        }
    }

    #[Test]
    public function testSetupPublishesInMemorySessionToAccessor(): void
    {
        $injector = new Injector(new TopLevel());
        $accessor = new SessionAccessor();
        $injector->setInstance(SessionAccess::class, $accessor);
        // HordeSessionFactory::create() resolves Horde_Secret_Cbc for
        // its encryptor closures; a stub is enough because this test
        // never encrypts anything.
        $injector->setInstance(
            'Horde_Secret_Cbc',
            $this->createStub(Horde_Core_Secret_Cbc::class),
        );
        $GLOBALS['injector'] = $injector;

        self::assertFalse(
            $accessor->hasCurrent(),
            'accessor must start empty — this is the state horde/Core#207 reported',
        );

        $null = new Horde_Session_Null();
        // Do not call setup() here: the parent's setup() calls
        // session_start()/session_write_close() which is unsuitable for
        // a unit test under phpunit. The publish path runs from
        // _start(), which is what setup() eventually calls; invoke it
        // directly to isolate the publish step from PHP's session
        // machinery.
        (new \ReflectionMethod($null, '_start'))->invoke($null);

        self::assertTrue(
            $accessor->hasCurrent(),
            'Horde_Session_Null::_start() must publish a HordeSession to the accessor',
        );
        self::assertInstanceOf(HordeSession::class, $accessor->current());
    }

    #[Test]
    public function testPublishedSessionIsAlsoRegisteredAsSharedSingleton(): void
    {
        $injector = new Injector(new TopLevel());
        $accessor = new SessionAccessor();
        $injector->setInstance(SessionAccess::class, $accessor);
        $injector->setInstance(
            'Horde_Secret_Cbc',
            $this->createStub(Horde_Core_Secret_Cbc::class),
        );
        $GLOBALS['injector'] = $injector;

        $null = new Horde_Session_Null();
        (new \ReflectionMethod($null, '_start'))->invoke($null);

        // Consumers that resolve HordeSession directly (not through the
        // accessor) must reach the same instance the accessor points at,
        // otherwise scoped writes through one route wouldn't be visible
        // through the other during the request.
        self::assertSame(
            $accessor->current(),
            $injector->getInstance(HordeSession::class),
            'HordeSession singleton and SessionAccessor current() diverged',
        );
    }

    #[Test]
    public function testScopedWritesFlowThroughAccessor(): void
    {
        // The whole point of publishing to the accessor is that
        // Nlsconfig-style callers (curr_default, valid_lang cache,
        // etc.) can call SessionAccess::setScoped()/getScoped()
        // without fataling. This test proves the round trip.
        $injector = new Injector(new TopLevel());
        $accessor = new SessionAccessor();
        $injector->setInstance(SessionAccess::class, $accessor);
        $injector->setInstance(
            'Horde_Secret_Cbc',
            $this->createStub(Horde_Core_Secret_Cbc::class),
        );
        $GLOBALS['injector'] = $injector;

        $null = new Horde_Session_Null();
        (new \ReflectionMethod($null, '_start'))->invoke($null);

        $accessor->setScoped('horde', 'nls/curr_default', 'de_DE');
        self::assertTrue($accessor->hasScoped('horde', 'nls/curr_default'));
        self::assertSame(
            'de_DE',
            $accessor->getScoped('horde', 'nls/curr_default'),
        );
    }

    #[Test]
    public function testStartDoesNotMarkPersistedSessionActive(): void
    {
        // Horde_Session_Null publishes to the accessor but must not
        // pretend to have a persisted PHP session. session_status()
        // must remain PHP_SESSION_NONE from the trait's perspective;
        // we test the publish path in isolation (bypassing the
        // parent's session_start()) and verify no session was
        // opened by the publish step itself.
        $injector = new Injector(new TopLevel());
        $accessor = new SessionAccessor();
        $injector->setInstance(SessionAccess::class, $accessor);
        $injector->setInstance(
            'Horde_Secret_Cbc',
            $this->createStub(Horde_Core_Secret_Cbc::class),
        );
        $GLOBALS['injector'] = $injector;

        // Bail out cleanly if the surrounding suite already opened a
        // session — the assertion below would be meaningless.
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::markTestSkipped('a session is already active in this process');
        }

        $null = new Horde_Session_Null();
        (new \ReflectionMethod($null, '_start'))->invoke($null);

        self::assertNotSame(
            PHP_SESSION_ACTIVE,
            session_status(),
            'the publish path must not call session_start()',
        );
    }
}
