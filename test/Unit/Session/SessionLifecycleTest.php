<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Session;

use Horde\Core\Config\State;
use Horde\Core\Secret\SessionSecret;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionAccess;
use Horde\Core\Session\SessionAccessor;
use Horde\Core\Session\SessionConfigFactory;
use Horde\Core\Session\SessionLifecycle;
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use Horde\SessionHandler\SessionHandler;
use Horde\SessionHandler\SessionId;
use Horde\SessionHandler\Storage\BuiltinBackend;
use Horde_Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Closure;
use Horde_Core_Secret_Cbc;
use Horde_Shutdown;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

use const E_WARNING;

/**
 * Unit tests for {@see SessionLifecycle}.
 *
 * Most lifecycle methods (setup with start=true, start, clean, destroy,
 * close, regenerate) wrap PHP-native session_* functions and are tested in
 * the integration suite, where a real session can be opened. This file
 * pins the parts of the contract that are exercisable without booting PHP
 * session state:
 *
 * - Cookie-domain guard in setup() with start=false.
 * - regenerationDue() pure deadline check.
 * - isActive() flag default state.
 * - shutdown() task is idempotent when inactive.
 * - shutdown() task mirrors HordeSession::toPayload() into $_SESSION when
 *   active (active flag is private, exercised via reflection here to
 *   avoid the session_start dependency).
 */
#[CoversClass(SessionLifecycle::class)]
class SessionLifecycleTest extends TestCase
{
    /**
     * Build a SessionLifecycle wired against an in-memory Injector so the
     * lifecycle's lazy HordeSession resolution works without a real
     * session_start.
     *
     * @param array<string,mixed> $conf
     */
    private function build(
        array $conf = [],
        ?HordeSession $session = null,
        ?SessionSecret $secret = null,
    ): SessionLifecycle {
        $injector = new Injector(new TopLevel());
        $session ??= new HordeSession(new SessionId('test-id'), []);
        $injector->setInstance(HordeSession::class, $session);

        $handler = new SessionHandler(new BuiltinBackend());
        $config = (new SessionConfigFactory())->fromState(new State($conf));

        return new SessionLifecycle($injector, $handler, $config, $secret);
    }

    // ---------------------------------------------------------------
    // Construction
    // ---------------------------------------------------------------

    #[Test]
    public function testCanBeConstructedWithMinimalDependencies(): void
    {
        $lifecycle = $this->build();
        self::assertInstanceOf(SessionLifecycle::class, $lifecycle);
    }

    #[Test]
    public function testIsInactiveByDefault(): void
    {
        self::assertFalse($this->build()->isActive());
    }

    #[Test]
    public function testAcceptsSessionSecretImplementation(): void
    {
        // Regression sentinel for the binding-name-vs-class confusion.
        // SessionLifecycle's secret arg is typed against the
        // SessionSecret interface, not the legacy Horde_Secret_Cbc
        // binding name. Anything that implements the interface
        // (including production's Horde_Core_Secret_Cbc) must satisfy
        // the constructor type check.
        $secret = $this->createStub(SessionSecret::class);
        $lifecycle = $this->build(secret: $secret);
        self::assertInstanceOf(SessionLifecycle::class, $lifecycle);
    }

    #[Test]
    public function testAcceptsHordeCoreSecretCbcViaInterfaceContract(): void
    {
        // Belt-and-braces: the actual production class is
        // Horde_Core_Secret_Cbc which now implements SessionSecret.
        // The class is loadable in unit context (no DB / no session
        // module needed for the constructor itself) so we can
        // instantiate it without arguments and confirm it satisfies
        // the lifecycle's type constraint.
        if (!class_exists(Horde_Core_Secret_Cbc::class)) {
            self::markTestSkipped('Horde_Core_Secret_Cbc not loadable in this test environment');
        }
        // Construct with the minimum params the new validator
        // requires: a non-empty secret_key under the default HKDF
        // format. The test asserts the type contract, not the
        // encryption behaviour.
        $secret = new Horde_Core_Secret_Cbc([
            'iv' => str_repeat("\0", 8),
            'secret_key' => 'sessionlifecycle-test-master',
        ]);
        self::assertInstanceOf(SessionSecret::class, $secret);

        $lifecycle = $this->build(secret: $secret);
        self::assertInstanceOf(SessionLifecycle::class, $lifecycle);
    }

    // ---------------------------------------------------------------
    // Cookie-domain guard
    // ---------------------------------------------------------------

    #[Test]
    public function testCookieDomainGuardThrowsOnSingleLabelHostname(): void
    {
        $lifecycle = $this->build([
            'cookie' => ['domain' => '.example.com'],
            'server' => ['name' => 'localhost'],
        ]);

        $this->expectException(Horde_Exception::class);
        $this->expectExceptionMessageMatches('/single-label hostname/');

        $lifecycle->setup(start: false);
    }

    #[Test]
    public function testSetupIsIdempotentOnRepeatedCall(): void
    {
        // Build with FQDN config so the first setup() succeeds.
        $lifecycle = $this->build([
            'session' => ['name' => 'Horde'],
            'cookie' => ['domain' => '.example.com'],
            'server' => ['name' => 'horde.example.com'],
        ]);

        // setup() registers a Horde_Shutdown task which reaches for
        // $GLOBALS['injector']. Provide a minimal stub to satisfy that
        // path; the shutdown task itself never fires under unit test.
        $injector = new Injector(new TopLevel());
        $injector->setInstance('Horde_Shutdown', new Horde_Shutdown());
        $previousInjector = $GLOBALS['injector'] ?? null;
        $GLOBALS['injector'] = $injector;

        try {
            $lifecycle->setup(start: false);

            // First call set the flag.
            $r = new ReflectionProperty(SessionLifecycle::class, 'setupApplied');
            $r->setAccessible(true);
            self::assertTrue($r->getValue($lifecycle));

            // Second call must not throw and must not mutate state.
            $lifecycle->setup(start: false);
            self::assertTrue($r->getValue($lifecycle));
        } finally {
            if ($previousInjector === null) {
                unset($GLOBALS['injector']);
            } else {
                $GLOBALS['injector'] = $previousInjector;
            }
        }
    }

    // The happy paths through setup() (FQDN hostname, empty cookie domain)
    // touch session_set_cookie_params, session_set_save_handler and the
    // global Horde_Shutdown registry. They are covered in the integration
    // suite where a real PHP session is available. The negative test
    // above suffices for unit-level coverage because the guard runs
    // before any side effects.

    // ---------------------------------------------------------------
    // regenerationDue()
    // ---------------------------------------------------------------

    #[Test]
    public function testRegenerationDueIsFalseWhenDeadlineNotSet(): void
    {
        // Default HordeSession has no _r key set.
        self::assertFalse($this->build()->regenerationDue());
    }

    #[Test]
    public function testRegenerationDueIsFalseWhenDeadlineInFuture(): void
    {
        $session = new HordeSession(new SessionId('rd-test'), []);
        $session->setRegenerationDeadline(time() + 3600);
        self::assertFalse($this->build(session: $session)->regenerationDue());
    }

    #[Test]
    public function testRegenerationDueIsTrueWhenDeadlineInPast(): void
    {
        $session = new HordeSession(new SessionId('rd-test'), []);
        $session->setRegenerationDeadline(time() - 1);
        self::assertTrue($this->build(session: $session)->regenerationDue());
    }

    #[Test]
    public function testRegenerationDueIgnoresLegacyScopedShape(): void
    {
        // Pre-fix _r writers used setScoped(REGENERATE_KEY, '', $ts).
        // Sessions still on disk in that shape return null from
        // getRegenerationDeadline and false from regenerationDue. Both
        // shapes converge on the next regenerate() / initialiseTimestamps
        // pass once the typed setters land.
        $session = new HordeSession(new SessionId('rd-test'), []);
        $session->setScoped('_r', '', time() - 1);
        self::assertFalse($this->build(session: $session)->regenerationDue());
    }

    #[Test]
    public function testRegenerationDueReadsViaHordeSessionNotSuperglobal(): void
    {
        // Layering: SessionLifecycle is the orchestrator; HordeSession owns
        // session data reads. Putting the deadline only in $_SESSION must
        // NOT make regenerationDue() see it.
        $previous = $_SESSION ?? null;
        $_SESSION = ['_r' => time() - 1];

        try {
            $session = new HordeSession(new SessionId('rd-test'), []);
            // No setRegenerationDeadline; HordeSession has no idea about the deadline.
            self::assertFalse($this->build(session: $session)->regenerationDue());
        } finally {
            if ($previous === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previous;
            }
        }
    }

    // ---------------------------------------------------------------
    // shutdown() task
    // ---------------------------------------------------------------

    #[Test]
    public function testShutdownIsNoOpWhenInactive(): void
    {
        $previous = $_SESSION ?? null;
        $_SESSION = ['marker' => 'untouched'];

        try {
            $this->build()->shutdown();
            self::assertSame(['marker' => 'untouched'], $_SESSION);
        } finally {
            if ($previous === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previous;
            }
        }
    }

    #[Test]
    public function testShutdownMirrorsHordeSessionPayloadWhenActive(): void
    {
        $session = new HordeSession(new SessionId('mirror-test'), []);
        // Signature is setScoped(string $app, string $name, mixed $value).
        $session->setScoped('horde', 'auth/userId', 'alice');

        $lifecycle = $this->build(session: $session);

        // Force active=true. We avoid invoking start() here because that
        // would require a real PHP session. The active flag is private
        // state owned by this class; flipping it via reflection mirrors
        // what start() would do.
        $r = new ReflectionProperty($lifecycle, 'active');
        $r->setAccessible(true);
        $r->setValue($lifecycle, true);

        $previous = $_SESSION ?? null;
        $_SESSION = [];

        try {
            $lifecycle->shutdown();
            self::assertArrayHasKey('horde', $_SESSION);
            self::assertSame("\0alice", $_SESSION['horde']['auth/userId']);
        } finally {
            if ($previous === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previous;
            }
        }
    }

    #[Test]
    public function testShutdownSkipsMirrorWhenSessionMarkedDestroyed(): void
    {
        // Usage error scenario: a controller calls markDestroyed()
        // without going through a synchronous executor, so the marker
        // is still pending when the request-shutdown task fires. The
        // shutdown task must NOT mirror the about-to-be-destroyed
        // payload back into $_SESSION; otherwise the session row gets
        // re-persisted with stale data the controller already
        // declared dead.
        $session = new HordeSession(new SessionId('destroyed-mirror'), []);
        $session->setScoped('horde', 'auth/userId', 'alice');
        $session->markDestroyed();

        $lifecycle = $this->build(session: $session);

        $r = new ReflectionProperty($lifecycle, 'active');
        $r->setAccessible(true);
        $r->setValue($lifecycle, true);

        $previous = $_SESSION ?? null;
        $_SESSION = ['untouched' => 'sentinel'];

        try {
            $lifecycle->shutdown();
            self::assertSame(
                ['untouched' => 'sentinel'],
                $_SESSION,
                'shutdown must not mirror when isDestroyed() is set'
            );
        } finally {
            if ($previous === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previous;
            }
        }
    }

    // ---------------------------------------------------------------
    // processFlags()
    //
    // The dispatch policy is tested via a recording subclass that
    // overrides the synchronous executors. Dispatching to the real
    // executors requires a live PHP session (session_regenerate_id /
    // session_destroy fail in CLI), which is the integration suite's
    // territory. Unit tests stay focused on the policy: who gets
    // called, in what order, and when nothing should happen.
    // ---------------------------------------------------------------

    private function buildRecording(?HordeSession $session = null): RecordingSessionLifecycle
    {
        $injector = new Injector(new TopLevel());
        $session ??= new HordeSession(new SessionId('process-flags-test'), []);
        $injector->setInstance(HordeSession::class, $session);
        $handler = new SessionHandler(new BuiltinBackend());
        $config = (new SessionConfigFactory())->fromState(new State([]));
        return new RecordingSessionLifecycle($injector, $handler, $config);
    }

    #[Test]
    public function testProcessFlagsNoOpWhenNoFlagsSet(): void
    {
        $session = new HordeSession(new SessionId('pf-noop'), []);
        $lifecycle = $this->buildRecording($session);

        $lifecycle->processFlags($session);

        self::assertSame([], $lifecycle->calls);
    }

    #[Test]
    public function testProcessFlagsDispatchesToRegenerateWhenFlagSet(): void
    {
        $session = new HordeSession(new SessionId('pf-regen'), []);
        $session->scheduleRegeneration();
        $lifecycle = $this->buildRecording($session);

        $lifecycle->processFlags($session);

        self::assertSame(['regenerate'], $lifecycle->calls);
    }

    #[Test]
    public function testProcessFlagsDispatchesToDestroyWhenFlagSet(): void
    {
        $session = new HordeSession(new SessionId('pf-destroy'), []);
        $session->markDestroyed();
        $lifecycle = $this->buildRecording($session);

        $lifecycle->processFlags($session);

        self::assertSame(['destroy'], $lifecycle->calls);
    }

    #[Test]
    public function testProcessFlagsPrefersDestroyOverRegenerate(): void
    {
        // Resolution policy: destruction wins. A session being destroyed
        // need not bother rotating its id.
        $session = new HordeSession(new SessionId('pf-both'), []);
        $session->scheduleRegeneration();
        $session->markDestroyed();
        $lifecycle = $this->buildRecording($session);

        $lifecycle->processFlags($session);

        self::assertSame(['destroy'], $lifecycle->calls);
    }

    #[Test]
    public function testProcessFlagsIsIdempotentOnRepeatedCall(): void
    {
        // Once executors clear flags after acting, a second processFlags()
        // call sees no flags and dispatches nothing.
        $session = new HordeSession(new SessionId('pf-idempotent'), []);
        $session->scheduleRegeneration();
        $lifecycle = $this->buildRecording($session);

        $lifecycle->processFlags($session);
        $lifecycle->processFlags($session);

        self::assertSame(['regenerate'], $lifecycle->calls);
    }

    #[Test]
    public function testShutdownDoesNotFireProcessFlags(): void
    {
        // Regression sentinel for the "no magic last-minute behaviour"
        // rule. shutdown() mirrors only; lifecycle dispatch must be
        // explicit, called by middleware or controllers, never auto-fired
        // from a shutdown hook.
        $session = new HordeSession(new SessionId('shutdown-no-dispatch'), []);
        $session->scheduleRegeneration();
        $session->markDestroyed();
        $lifecycle = $this->buildRecording($session);

        // Force active=true so shutdown() does its mirror; processFlags
        // should still NOT fire.
        $r = new ReflectionProperty(SessionLifecycle::class, 'active');
        $r->setAccessible(true);
        $r->setValue($lifecycle, true);

        $previous = $_SESSION ?? null;
        $_SESSION = [];

        try {
            $lifecycle->shutdown();
        } finally {
            if ($previous === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previous;
            }
        }

        self::assertSame([], $lifecycle->calls);
        self::assertTrue($session->shouldRegenerate());
        self::assertTrue($session->isDestroyed());
    }

    // ---------------------------------------------------------------
    // regenerate() — wiring the re-encryption dance
    //
    // The real regenerate() calls session_regenerate_id(true), which
    // fails in CLI without a live PHP session. To exercise the wiring
    // without booting one, we install a CapturingHordeSession test
    // double in the injector. The double captures the callback that
    // SessionLifecycle::regenerate() hands to reEncryptAll(), so the
    // test can pin (a) that reEncryptAll IS called, and (b) that the
    // callback wires through to the secret service's setKey() — both
    // without invoking session_regenerate_id.
    // ---------------------------------------------------------------

    #[Test]
    public function testRegenerateDelegatesToReEncryptAll(): void
    {
        $session = new CapturingHordeSession(new SessionId('regen-wiring'), []);
        $secret = new RecordingSessionSecret();
        $lifecycle = $this->build([], $session, $secret);

        try {
            $lifecycle->regenerate();
        } catch (Throwable $e) {
            self::fail('regenerate threw unexpectedly: ' . $e->getMessage());
        }

        self::assertTrue($session->reEncryptAllCalled, 'reEncryptAll invoked');
        self::assertNotNull($session->capturedRotation, 'rotation callback captured');
    }

    #[Test]
    public function testRegenerateCallbackInvokesSecretSetKey(): void
    {
        $session = new CapturingHordeSession(new SessionId('regen-setkey'), []);
        $secret = new RecordingSessionSecret();
        $lifecycle = $this->build([], $session, $secret);

        $lifecycle->regenerate();

        self::assertTrue($secret->setKeyCalled, 'rotation callback calls secret->setKey()');
    }

    #[Test]
    public function testRegenerateUpdatesDeadline(): void
    {
        $session = new CapturingHordeSession(new SessionId('regen-deadline'), []);
        $lifecycle = $this->build(
            ['session' => ['regenerate_interval' => 60]],
            $session,
        );

        $beforeDeadline = $session->getRegenerationDeadline();

        $lifecycle->regenerate();

        $afterDeadline = $session->getRegenerationDeadline();
        self::assertNotSame($beforeDeadline, $afterDeadline);
        self::assertIsInt($afterDeadline);
    }

    #[Test]
    public function testRegenerateClearsLifecycleFlags(): void
    {
        $session = new CapturingHordeSession(new SessionId('regen-flags'), []);
        $session->scheduleRegeneration();
        $lifecycle = $this->build([], $session);

        $lifecycle->regenerate();

        self::assertFalse($session->shouldRegenerate());
    }

    // ---------------------------------------------------------------
    // SessionAccess-mediated regeneration invariants (horde/Core#190).
    //
    // Before the SessionAccess split, consumers that captured a
    // HordeSession via constructor injection (private readonly
    // HordeSession $session) held a stale reference across a
    // regenerate() call: rebuildHordeSession() published a fresh
    // instance to the injector, but the already-handed-out reference
    // was frozen. LoginService, whups controllers, and every other
    // Category-A/B holder in the fleet had this shape.
    //
    // The fix moves consumers to `private readonly SessionAccess $session`.
    // SessionAccess is a request-scoped slot pointer; every read
    // consults SessionLifecycle's canonical HordeSession at call time.
    // These tests pin the invariant end-to-end: a SessionAccess
    // captured *before* regenerate() sees the post-regenerate value
    // on its next read.
    // ---------------------------------------------------------------

    /**
     * Build a SessionLifecycle with a pinned {@see SessionAccessor} so
     * tests can pre-capture the accessor (mimicking a consumer's
     * constructor-injected `SessionAccess`) and prove it points at the
     * canonical instance after regenerate().
     *
     * @param array<string,mixed> $conf
     */
    private function buildWithAccess(
        SessionAccessor $accessor,
        array $conf = [],
        ?HordeSession $session = null,
        ?SessionSecret $secret = null,
    ): SessionLifecycle {
        $injector = new Injector(new TopLevel());
        $session ??= new HordeSession(new SessionId('access-test'), []);
        $injector->setInstance(HordeSession::class, $session);
        $injector->setInstance(SessionAccess::class, $accessor);
        $accessor->replaceWith($session);

        $handler = new SessionHandler(new BuiltinBackend());
        $config = (new SessionConfigFactory())->fromState(new State($conf));

        return new SessionLifecycle(
            $injector,
            $handler,
            $config,
            $secret,
            null,
            $accessor,
        );
    }

    #[Test]
    public function testCapturedSessionAccessReferenceSeesRegeneratedInstance(): void
    {
        // Mirror of the LoginService pattern: a consumer captures the
        // SessionAccess at construction and holds it across a
        // regenerate() call. The captured reference must resolve to
        // the canonical (post-regenerate) HordeSession on its next
        // read.
        //
        // Uses the reEncryptAll fallback path (no coordinator wired)
        // because a coordinator-driven regenerate needs session_id()
        // to return the id PHP has post-rotation, which we can't
        // reliably produce in CLI without booting a real session.
        // Under the fallback, regenerate() mutates the same
        // HordeSession in place and calls publishSession() to refresh
        // the accessor pointer. The captured reference is the
        // accessor; the pointer swap is invisible to the caller
        // because they always read via current().

        $accessor = new SessionAccessor();
        $session = new CapturingHordeSession(new SessionId('access-regen'), []);
        $lifecycle = $this->buildWithAccess(
            $accessor,
            [],
            $session,
        );

        // Simulate a consumer capturing SessionAccess at construction.
        $capturedRef = $accessor;

        $lifecycle->regenerate();

        // The captured reference resolves to the canonical HordeSession
        // — the one SessionLifecycle just finished mutating. Before the
        // SessionAccess fix, a consumer that captured HordeSession
        // directly would see a divergent instance here.
        self::assertTrue(
            $capturedRef->hasCurrent(),
            'captured SessionAccess must resolve after regenerate',
        );
        self::assertSame(
            $session,
            $capturedRef->current(),
            'captured SessionAccess resolves to the lifecycle-managed HordeSession',
        );
    }

    #[Test]
    public function testSessionAccessPassthroughReadReflectsPostRegenerateState(): void
    {
        // A consumer that stores something in a scoped slot before
        // regenerate() and reads it back via SessionAccess after
        // regenerate() must see the value. Under the reEncryptAll
        // fallback path this is trivially true (same object, in-place
        // mutation), but the test pins the invariant end-to-end
        // through the passthrough facade.

        $accessor = new SessionAccessor();
        $session = new CapturingHordeSession(new SessionId('access-scoped'), []);
        $lifecycle = $this->buildWithAccess($accessor, [], $session);

        // Simulate a consumer writing a scoped value before regen.
        $accessor->setScoped('imp', 'user_pref', 'value-before-regen');

        $lifecycle->regenerate();

        // Consumer's next read via the captured accessor sees the
        // value. This is what jcdelepine's log showed failing: post-
        // regen reads via the shim's captured $this->modern saw
        // stale/wrong state.
        self::assertSame(
            'value-before-regen',
            $accessor->getScoped('imp', 'user_pref'),
            'post-regenerate read via SessionAccess sees the pre-regen write',
        );
    }

    #[Test]
    public function testTwoAccessReadsInSameRequestReturnSameInstance(): void
    {
        // Correctness invariant: within one request, SessionAccess::current()
        // returns the same HordeSession value across successive calls
        // as long as no lifecycle transition has run in between. If a
        // second call returned a different object silently, encrypted
        // slot semantics would break (each object gets its own
        // ciphertext view). Guards against a regression where the
        // accessor might re-resolve from the injector on every call.

        $accessor = new SessionAccessor();
        $session = new CapturingHordeSession(new SessionId('access-stable'), []);
        $this->buildWithAccess($accessor, [], $session);

        $first = $accessor->current();
        $second = $accessor->current();

        self::assertSame(
            $first,
            $second,
            'accessor returns the same HordeSession object across calls '
            . 'until a lifecycle transition replaces it',
        );
    }

    #[Test]
    public function testLifecycleWorksWithoutSessionAccessBinding(): void
    {
        // BC guarantee: legacy test/bootstrap contexts that build a
        // SessionLifecycle without wiring SessionAccess still work.
        // The lifecycle falls back to a private, non-shared accessor;
        // consumers reading SessionAccess in those contexts get an
        // independent instance (which is the sensible behavior for
        // partial DI setups — they don't have consumers to keep in
        // sync in the first place).

        $session = new CapturingHordeSession(new SessionId('bc-no-access'), []);
        $lifecycle = $this->build([], $session);

        try {
            $lifecycle->regenerate();
        } catch (Throwable $e) {
            self::fail(
                'regenerate() must not throw when SessionAccess is not '
                . 'bound: ' . $e->getMessage(),
            );
        }

        self::assertTrue($session->reEncryptAllCalled);
    }

    // ---------------------------------------------------------------
    // Coordinator-path invariants: this is where the horde/Core#190
    // fix actually differs from the pre-fix behavior. Pre-fix, the
    // coordinator's refill() mutated the pre-regen HordeSession in
    // place — captured references saw the id-and-salt drift but the
    // *object identity* was preserved, and reads through those
    // captured refs got the (mutated) post-regen state anyway.
    //
    // Under the fix, regenerate() mints a *fresh* HordeSession value
    // (immutable SessionId semantics: a rotated session is a
    // different session), and only the SessionAccess slot's pointer
    // update reaches consumers. A captured HordeSession reference
    // stays frozen on the pre-regen instance; a captured SessionAccess
    // reference resolves to the fresh one on every read.
    //
    // These tests pin that observable difference.
    // ---------------------------------------------------------------

    /**
     * Build a lifecycle with a real {@see SessionEncryptionCoordinator}
     * (backed by a recording SessionSecret) and a real
     * {@see HordeSessionFactory}. This drives the coordinator branch
     * of `regenerate()` — mint a successor via `mintSuccessor()`, rekey
     * and refill into it, publish it via the accessor.
     *
     * `session_regenerate_id(true)` inside regenerate() emits a
     * warning in CLI (no live PHP session). We suppress it for the
     * duration of the regenerate() call so failOnWarning doesn't
     * trip; the observable invariants (accessor points at fresh
     * instance, old ref unchanged) are independent of whether the
     * id-rotation call itself succeeded.
     */
    private function runCoordinatorRegenerate(
        SessionLifecycle $lifecycle,
    ): void {
        set_error_handler(static fn() => true, E_WARNING);
        try {
            $lifecycle->regenerate();
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function testCoordinatorRegenerateMintsFreshHordeSessionAndPublishesIt(): void
    {
        // Core invariant of the fix: the coordinator path produces a
        // NEW HordeSession value under regenerate() and publishes it
        // to the accessor. A captured SessionAccess reference reads
        // through to the new instance on its next call.
        //
        // This is the test that distinguishes fixed-vs-broken code
        // for horde/Core#190. Before the fix, coordinator->refill()
        // mutated the pre-regen instance in place; after the fix,
        // mintSuccessor() constructs a new one. If publishSession()
        // stops updating the accessor, this test fails.

        $accessor = new SessionAccessor();
        $secret = new RecordingSessionSecret();
        $coordinator = new \Horde\Core\Session\SessionEncryptionCoordinator($secret);

        $pre = new HordeSession(new SessionId('coord-pre'), []);

        $injector = new Injector(new TopLevel());
        $injector->setInstance(HordeSession::class, $pre);
        $injector->setInstance(SessionAccess::class, $accessor);
        $injector->setInstance(
            \Horde\Core\Session\HordeSessionFactory::class,
            new \Horde\Core\Session\HordeSessionFactory(),
        );
        $accessor->replaceWith($pre);

        $handler = new SessionHandler(new BuiltinBackend());
        $config = (new SessionConfigFactory())->fromState(new State([]));

        $lifecycle = new SessionLifecycle(
            $injector,
            $handler,
            $config,
            $secret,
            $coordinator,
            $accessor,
        );

        // Capture the accessor as if we were LoginService.
        $capturedAccess = $accessor;

        $this->runCoordinatorRegenerate($lifecycle);

        // The successor must be a distinct HordeSession instance.
        // This is the observable difference between the pre-fix
        // (in-place mutation) and post-fix (mint successor) shapes.
        $post = $capturedAccess->current();
        self::assertNotSame(
            $pre,
            $post,
            'coordinator regenerate must mint a fresh HordeSession value',
        );
        self::assertInstanceOf(HordeSession::class, $post);
    }

    #[Test]
    public function testCoordinatorRegenerateBindsSecretToSuccessorSession(): void
    {
        // The rekey() step must point the SessionSecret at the fresh
        // successor before setKey() writes the new salt. Otherwise
        // the salt lands on the pre-regen session (whose ciphertext
        // slots we're not going to refill) and the successor reads
        // its salt from an empty slot.
        //
        // Verifies the rekey() half of the drain/rekey/refillInto
        // primitives split from the old refill().

        $accessor = new SessionAccessor();
        $secret = new RecordingSessionSecret();
        $coordinator = new \Horde\Core\Session\SessionEncryptionCoordinator($secret);

        $pre = new HordeSession(new SessionId('coord-secret-bind'), []);

        $injector = new Injector(new TopLevel());
        $injector->setInstance(HordeSession::class, $pre);
        $injector->setInstance(SessionAccess::class, $accessor);
        $injector->setInstance(
            \Horde\Core\Session\HordeSessionFactory::class,
            new \Horde\Core\Session\HordeSessionFactory(),
        );
        $accessor->replaceWith($pre);

        $lifecycle = new SessionLifecycle(
            $injector,
            new SessionHandler(new BuiltinBackend()),
            (new SessionConfigFactory())->fromState(new State([])),
            $secret,
            $coordinator,
            $accessor,
        );

        $this->runCoordinatorRegenerate($lifecycle);

        // The secret's setSession() must have been called with the
        // successor, not the predecessor. RecordingSessionSecret
        // remembers the last one.
        self::assertNotNull(
            $secret->wiredSession,
            'coordinator regenerate must wire the secret to a session',
        );
        self::assertNotSame(
            $pre,
            $secret->wiredSession,
            'secret must be bound to the successor, not the pre-regen session',
        );
    }

    #[Test]
    public function testCoordinatorRegenerateCallsSecretSetKeyOnce(): void
    {
        // The refill()'s "setKey once per rotation" contract survives
        // the split into rekey() + refillInto().

        $accessor = new SessionAccessor();
        $secret = new RecordingSessionSecret();
        $coordinator = new \Horde\Core\Session\SessionEncryptionCoordinator($secret);

        $pre = new HordeSession(new SessionId('coord-setkey'), []);

        $injector = new Injector(new TopLevel());
        $injector->setInstance(HordeSession::class, $pre);
        $injector->setInstance(SessionAccess::class, $accessor);
        $injector->setInstance(
            \Horde\Core\Session\HordeSessionFactory::class,
            new \Horde\Core\Session\HordeSessionFactory(),
        );
        $accessor->replaceWith($pre);

        $lifecycle = new SessionLifecycle(
            $injector,
            new SessionHandler(new BuiltinBackend()),
            (new SessionConfigFactory())->fromState(new State([])),
            $secret,
            $coordinator,
            $accessor,
        );

        $this->runCoordinatorRegenerate($lifecycle);

        self::assertTrue(
            $secret->setKeyCalled,
            'rekey must call secret->setKey() once per regenerate',
        );
    }

    #[Test]
    public function testCoordinatorRegenerateCarriesPlaintextDataForward(): void
    {
        // A scoped value written before regenerate() must be readable
        // via the accessor after regenerate(). mintSuccessor() feeds
        // the old session's toPayload() into HordeSessionFactory::restore(),
        // so scoped-slot data survives the transition. The failure
        // mode this guards against: mintSuccessor() constructing an
        // empty-payload successor and silently dropping every user
        // preference and cache slot.

        $accessor = new SessionAccessor();
        $secret = new RecordingSessionSecret();
        $coordinator = new \Horde\Core\Session\SessionEncryptionCoordinator($secret);

        $pre = new HordeSession(new SessionId('coord-plain-forward'), []);
        $pre->setScoped('imp', 'ui_layout', ['sidebar' => 'left']);

        $injector = new Injector(new TopLevel());
        $injector->setInstance(HordeSession::class, $pre);
        $injector->setInstance(SessionAccess::class, $accessor);
        $injector->setInstance(
            \Horde\Core\Session\HordeSessionFactory::class,
            new \Horde\Core\Session\HordeSessionFactory(),
        );
        $accessor->replaceWith($pre);

        $lifecycle = new SessionLifecycle(
            $injector,
            new SessionHandler(new BuiltinBackend()),
            (new SessionConfigFactory())->fromState(new State([])),
            $secret,
            $coordinator,
            $accessor,
        );

        $this->runCoordinatorRegenerate($lifecycle);

        self::assertSame(
            ['sidebar' => 'left'],
            $accessor->getScoped('imp', 'ui_layout'),
            'scoped values written pre-regenerate must be readable '
            . 'via the accessor after regenerate',
        );
    }
}

/**
 * HordeSession test double that captures the callback handed to
 * reEncryptAll() and runs it under a warning-tolerant error handler.
 * Lets us pin the SessionLifecycle::regenerate() wiring at unit
 * scope without booting a real PHP session — session_regenerate_id()
 * inside the callback emits a CLI warning that would otherwise trip
 * phpunit.xml.dist's failOnWarning rule.
 */
class CapturingHordeSession extends HordeSession
{
    public bool $reEncryptAllCalled = false;
    public ?Closure $capturedRotation = null;

    public function reEncryptAll(Closure $rotate): void
    {
        $this->reEncryptAllCalled = true;
        $this->capturedRotation = $rotate;

        // Run the rotation but suppress the inevitable
        // "Session ID cannot be regenerated when there is no active
        // session" warning from session_regenerate_id(). The rest of
        // the callback (e.g. secret->setKey) still runs.
        set_error_handler(static fn() => true, E_WARNING);
        try {
            $rotate();
        } finally {
            restore_error_handler();
        }
    }
}

/**
 * SessionSecret test double that records setKey() invocations.
 */
class RecordingSessionSecret implements SessionSecret
{
    public bool $setKeyCalled = false;
    public bool $clearKeyCalled = false;
    public ?HordeSession $wiredSession = null;

    public function setKey($keyname = 'generic')
    {
        $this->setKeyCalled = true;
        return 'recorded-key';
    }

    public function clearKey($keyname = 'generic')
    {
        $this->clearKeyCalled = true;
        return true;
    }

    public function setSession(HordeSession $session): void
    {
        $this->wiredSession = $session;
    }
}

/**
 * Test double that records calls to the synchronous executors instead of
 * invoking PHP's session module. Lets us verify processFlags()'s dispatch
 * policy at unit-test scope without requiring a live session.
 */
class RecordingSessionLifecycle extends SessionLifecycle
{
    /** @var list<string> */
    public array $calls = [];

    public function clean(): bool
    {
        $this->calls[] = 'clean';
        $this->getHordeSessionForTest()->clearLifecycleFlags();
        return true;
    }

    public function destroy(): void
    {
        $this->calls[] = 'destroy';
        $this->getHordeSessionForTest()->clearLifecycleFlags();
    }

    public function regenerate(): void
    {
        $this->calls[] = 'regenerate';
        $this->getHordeSessionForTest()->clearLifecycleFlags();
    }

    /**
     * Resolve the modern session via the same injector path the parent
     * uses. A small accessor because the parent's getSession() is private.
     */
    private function getHordeSessionForTest(): HordeSession
    {
        $r = new ReflectionMethod(parent::class, 'getSession');
        $r->setAccessible(true);
        /** @var HordeSession $session */
        $session = $r->invoke($this);
        return $session;
    }
}
