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
 * Pin the legacy-only key-format behaviour.
 *
 * These tests originally pinned the PR-#174 session-payload key
 * persistence (`_secret/key` slot, cookie fallback). After HKDF
 * landed, that behaviour is reachable via the `legacy-only` value
 * of the `key_format` constructor param — kept as an operator-
 * controlled rollback path for severe HKDF-side bugs. Pinning it
 * here lets us confirm the rollback path stays intact independently
 * of any HKDF changes.
 *
 * The HKDF default path is tested in {@see CbcHkdfTest}. The
 * shape-2 → shape-3 migration path is tested in
 * {@see CbcMigrationTest}.
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
            // legacy-only matches the original PR-#174 behaviour
            // these tests were written to pin.
            'key_format' => Horde_Core_Secret_Cbc::KEY_FORMAT_LEGACY_ONLY,
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
    public function testGetKeyIgnoresCookieUnderLegacyOnlyMode(): void
    {
        // legacy-only mode is a rollback path, not a continuation
        // of any prior cookie state. The framework mints its own
        // random key and never adopts a value from
        // $_COOKIE['horde_secret_key']. A stale or attacker-planted
        // cookie value therefore cannot become the encryption key.
        $_COOKIE[self::SESSION_COOKIE] = 'sess';
        $_COOKIE[self::COOKIE_KEY] = 'cookie-key';
        $session = $this->buildSession();
        $cbc = $this->buildCbc($session);

        $key = $cbc->getKey();

        // The cookie value did NOT become the key — a fresh random
        // one was minted instead.
        self::assertNotSame('cookie-key', $key);
        self::assertNotSame('', $key);
        // The minted key now sits in the slot, so subsequent reads
        // are stable.
        self::assertTrue($session->hasScoped('_secret', 'key'));
        self::assertSame($key, $session->getScoped('_secret', 'key'));
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
    public function testSetKeyWritesToSessionSlotOnly(): void
    {
        // Under legacy-only, setKey writes the freshly minted key
        // to the session slot. It does NOT write to the legacy
        // `horde_secret_key` cookie. The cookie was historically
        // the authoritative key source pre-PR-#174; under the
        // three-state design it is no longer trusted (an attacker
        // who plants a cookie should not gain control of the
        // session's encryption key). Operators rolling back to
        // legacy-only get the PR-#174 slot behaviour without the
        // pre-PR-#174 cookie behaviour.
        $_COOKIE[self::SESSION_COOKIE] = 'sess';
        $session = $this->buildSession();
        $cbc = $this->buildCbc($session);

        $key = $cbc->setKey();

        self::assertNotSame('', $key);
        self::assertSame($key, $session->getScoped('_secret', 'key'));
        // Cookie is NOT written.
        self::assertArrayNotHasKey(self::COOKIE_KEY, $_COOKIE);
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
        // Sanity-check the full happy path: write encrypted data
        // while the cookie is present, then drop the cookie. The
        // session-stored key still lets us decrypt — and would
        // even if the cookie had never been there, because under
        // the new design the cookie is not a key source.
        $_COOKIE[self::SESSION_COOKIE] = 'sess';
        // Plant a recognisable cookie value to prove it is ignored.
        $_COOKIE[self::COOKIE_KEY] = str_repeat('K', 32);
        $session = $this->buildSession();
        $cbc = $this->buildCbc($session);

        $key1 = $cbc->getKey();
        // The cookie's planted value did not become the key.
        self::assertNotSame(str_repeat('K', 32), $key1);

        $cipher = $cbc->write($key1, 'secret-payload');

        // User refreshes; browser has dropped the key cookie. The
        // session payload still carries the freshly minted key.
        unset($_COOKIE[self::COOKIE_KEY]);

        $cbc2 = $this->buildCbc($session);
        $plain = $cbc2->read($cbc2->getKey(), $cipher);

        self::assertSame('secret-payload', $plain);
    }
}
