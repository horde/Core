<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Secret;

use Horde\Core\Session\HordeSession;
use Horde\SessionHandler\SessionId;
use Horde_Core_Secret;
use Horde_Core_Secret_Cbc;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pin the session-payload key persistence added to fix imp #66.
 *
 * The Blowfish key the per-session encrypted slots are bound to used to
 * live exclusively in the `horde_secret_key` cookie (falling back to
 * `session_id()` when the cookie was absent). Either path turned the key
 * into a moving target across upgrades, cookie eviction, and session-id
 * rotation. After this change the canonical home is the modern session
 * payload at slot `_secret`/`key`; the cookie remains as a BC fallback
 * and the value migrates into the payload on first read.
 */
#[CoversClass(Horde_Core_Secret_Cbc::class)]
class CbcTest extends TestCase
{
    /** Cookie name the legacy fallback writes to. */
    private const COOKIE_KEY = Horde_Core_Secret::HORDE_KEYNAME . '_key';

    /** Cookie name `Horde_Secret::setKey` checks to decide whether a session is "active". */
    private const SESSION_COOKIE = 'horde_secret';

    protected function setUp(): void
    {
        // Both Horde_Secret::setKey and ::getKey read $_COOKIE directly.
        // Wipe relevant entries between tests so cookie state from a
        // previous case does not leak.
        unset(
            $_COOKIE[self::COOKIE_KEY],
            $_COOKIE[self::SESSION_COOKIE],
        );
    }

    private function buildCbc(?HordeSession $session = null): Horde_Core_Secret_Cbc
    {
        $cbc = new Horde_Core_Secret_Cbc([
            'cookie_domain' => '',
            'cookie_path' => '/',
            'cookie_ssl' => false,
            'iv' => str_repeat("\0", 8),
            'session_name' => self::SESSION_COOKIE,
        ]);
        if ($session !== null) {
            $cbc->setSession($session);
        }
        return $cbc;
    }

    private function buildSession(): HordeSession
    {
        return new HordeSession(new SessionId('cbc-test'), []);
    }

    #[Test]
    public function testGetKeyReadsFromSessionWhenPresent(): void
    {
        $session = $this->buildSession();
        $session->setScoped('_secret', 'key', 'in-session-key');
        $cbc = $this->buildCbc($session);

        self::assertSame('in-session-key', $cbc->getKey());
    }

    #[Test]
    public function testGetKeyMigratesFromCookieWhenSessionSlotEmpty(): void
    {
        $_COOKIE[self::COOKIE_KEY] = 'cookie-key';
        $session = $this->buildSession();
        $cbc = $this->buildCbc($session);

        self::assertSame('cookie-key', $cbc->getKey());
        // The cookie value is now copied into the session payload so the
        // next read no longer depends on the cookie surviving.
        self::assertTrue($session->hasScoped('_secret', 'key'));
        self::assertSame('cookie-key', $session->getScoped('_secret', 'key'));
    }

    #[Test]
    public function testGetKeySessionWinsWhenBothPresent(): void
    {
        $_COOKIE[self::COOKIE_KEY] = 'cookie-key';
        $session = $this->buildSession();
        $session->setScoped('_secret', 'key', 'in-session-key');
        $cbc = $this->buildCbc($session);

        self::assertSame('in-session-key', $cbc->getKey());
    }

    #[Test]
    public function testGetKeyMintsFreshWhenNeitherPresent(): void
    {
        // Simulate an active session-name cookie so Horde_Secret::setKey
        // (called transitively from getKey when no key cookie is set)
        // takes the cookie-writing branch instead of falling through to
        // session_id().
        $_COOKIE[self::SESSION_COOKIE] = 'sess';
        $session = $this->buildSession();
        $cbc = $this->buildCbc($session);

        $key = $cbc->getKey();

        self::assertNotSame('', $key);
        self::assertTrue($session->hasScoped('_secret', 'key'));
        self::assertSame($key, $session->getScoped('_secret', 'key'));
    }

    #[Test]
    public function testFallsBackToLegacyBehaviourWithoutSessionWired(): void
    {
        // No setSession() call: the legacy code path stays unchanged
        // (cookie wins, no payload migration attempted).
        $_COOKIE[self::COOKIE_KEY] = 'cookie-key';
        $cbc = $this->buildCbc();

        self::assertSame('cookie-key', $cbc->getKey());
    }

    #[Test]
    public function testSetKeyWritesToSessionAndCookie(): void
    {
        $_COOKIE[self::SESSION_COOKIE] = 'sess';
        $session = $this->buildSession();
        $cbc = $this->buildCbc($session);

        $key = $cbc->setKey();

        self::assertNotSame('', $key);
        self::assertSame($key, $session->getScoped('_secret', 'key'));
        self::assertArrayHasKey(self::COOKIE_KEY, $_COOKIE);
        self::assertSame($key, $_COOKIE[self::COOKIE_KEY]);
    }

    #[Test]
    public function testClearKeyClearsSessionAndCookie(): void
    {
        $_COOKIE[self::SESSION_COOKIE] = 'sess';
        $_COOKIE[self::COOKIE_KEY] = 'cookie-key';
        $session = $this->buildSession();
        $session->setScoped('_secret', 'key', 'in-session-key');
        $cbc = $this->buildCbc($session);

        $cbc->clearKey();

        self::assertFalse($session->hasScoped('_secret', 'key'));
        self::assertArrayNotHasKey(self::COOKIE_KEY, $_COOKIE);
    }

    #[Test]
    public function testEncryptDecryptRoundTripAcrossCookieLoss(): void
    {
        // Sanity-check the full happy path: write encrypted data while
        // the cookie is present, then drop the cookie. The session-stored
        // key must still let us decrypt.
        $_COOKIE[self::SESSION_COOKIE] = 'sess';
        $_COOKIE[self::COOKIE_KEY] = str_repeat('K', 32);
        $session = $this->buildSession();
        $cbc = $this->buildCbc($session);

        $cipher = $cbc->write($cbc->getKey(), 'secret-payload');

        // User refreshes; browser has dropped the key cookie. Session
        // payload still carries the key.
        unset($_COOKIE[self::COOKIE_KEY]);

        $cbc2 = $this->buildCbc($session);
        $plain = $cbc2->read($cbc2->getKey(), $cipher);

        self::assertSame('secret-payload', $plain);
    }
}
