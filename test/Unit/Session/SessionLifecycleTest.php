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
use Horde\Core\Session\HordeSession;
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
    ): SessionLifecycle {
        $injector = new Injector(new TopLevel());
        $session ??= new HordeSession(new SessionId('test-id'), []);
        $injector->setInstance(HordeSession::class, $session);

        $handler = new SessionHandler(new BuiltinBackend());

        return new SessionLifecycle($injector, $handler, new State($conf));
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
        $previous = $_SESSION ?? null;
        $_SESSION = [];

        try {
            self::assertFalse($this->build()->regenerationDue());
        } finally {
            if ($previous === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previous;
            }
        }
    }

    #[Test]
    public function testRegenerationDueIsFalseWhenDeadlineInFuture(): void
    {
        $previous = $_SESSION ?? null;
        $_SESSION = ['_r' => time() + 3600];

        try {
            self::assertFalse($this->build()->regenerationDue());
        } finally {
            if ($previous === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previous;
            }
        }
    }

    #[Test]
    public function testRegenerationDueIsTrueWhenDeadlineInPast(): void
    {
        $previous = $_SESSION ?? null;
        $_SESSION = ['_r' => time() - 1];

        try {
            self::assertTrue($this->build()->regenerationDue());
        } finally {
            if ($previous === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previous;
            }
        }
    }

    #[Test]
    public function testRegenerationDueIgnoresNonIntegerDeadlines(): void
    {
        $previous = $_SESSION ?? null;
        $_SESSION = ['_r' => 'not-a-timestamp'];

        try {
            self::assertFalse($this->build()->regenerationDue());
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
        $r = new \ReflectionProperty($lifecycle, 'active');
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
}
