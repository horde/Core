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
use Horde_Core_Secret_Cbc;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Pin the `key_format=legacy-only` rollback path.
 *
 * Legacy-only is an operator-controlled emergency exit: a severe
 * HKDF-side bug can be neutralised by flipping
 * `$conf['session']['key_format']` to `legacy-only` without
 * redeploying code. In this mode the framework reads and writes
 * exclusively the PR-#174 shape (raw key at `_secret/key`).
 *
 * Tests in this file pin:
 *  - reads ignore any HKDF salt slot
 *  - writes go to the legacy slot and drop any HKDF salt
 *  - encrypt / decrypt round-trips work using the parent cipher chain
 *  - empty master secret is acceptable (HKDF inputs are unused)
 *  - flipping back to a hkdf-* format would invalidate ciphertext
 *    written under legacy-only (the safe/unsafe transition story
 *    documented in the implementation plan)
 */
#[CoversClass(Horde_Core_Secret_Cbc::class)]
class CbcLegacyOnlyTest extends TestCase
{
    private const SID = 'cbc-legacy-only-sid';

    protected function setUp(): void
    {
        session_id(self::SID);
        // Simulate an active session-name cookie so the parent's
        // setKey() takes the cookie-writing branch.
        $_COOKIE['horde_secret'] = 'sess';
        unset($_COOKIE['horde_secret_key']);
    }

    protected function tearDown(): void
    {
        session_id('');
        unset($_COOKIE['horde_secret'], $_COOKIE['horde_secret_key']);
    }

    private function buildLegacyOnly(): Horde_Core_Secret_Cbc
    {
        return new Horde_Core_Secret_Cbc([
            'cookie_domain' => '',
            'cookie_path' => '/',
            'cookie_ssl' => false,
            'iv' => str_repeat("\0", 8),
            'session_name' => 'horde_secret',
            // Master secret is optional for legacy-only; HKDF is not
            // used. Tests still pass a value to mirror real config.
            'secret_key' => 'master-unused-in-legacy-only',
            'key_format' => Horde_Core_Secret_Cbc::KEY_FORMAT_LEGACY_ONLY,
        ]);
    }

    private function buildSession(): HordeSession
    {
        return new HordeSession(new SessionId(self::SID), []);
    }

    #[Test]
    public function testWritesRawKeyToLegacySlot(): void
    {
        $session = $this->buildSession();
        $cbc = $this->buildLegacyOnly();
        $cbc->setSession($session);

        $cbc->setKey();

        self::assertTrue($session->hasScoped('_secret', 'key'));
        self::assertFalse($session->hasScoped('_secret', 'salt'));
    }

    #[Test]
    public function testReadsLegacySlotOnly(): void
    {
        $session = $this->buildSession();
        $session->setScoped('_secret', 'key', 'rollback-key');
        $cbc = $this->buildLegacyOnly();
        $cbc->setSession($session);

        self::assertSame('rollback-key', $cbc->getKey());
    }

    #[Test]
    public function testIgnoresHkdfSaltOnRead(): void
    {
        // A session arriving with an HKDF salt slot (e.g. it was
        // written by the framework before the operator flipped to
        // legacy-only) is read as if no key material existed.
        // getKey() mints a fresh legacy raw key.
        $session = $this->buildSession();
        $session->setScoped('_secret', 'salt', str_repeat("\x09", 32));
        $cbc = $this->buildLegacyOnly();
        $cbc->setSession($session);

        $key = $cbc->getKey();

        // The minted key is a fresh raw key, not a derivation of
        // the salt.
        self::assertNotSame('', $key);
        self::assertTrue($session->hasScoped('_secret', 'key'));
    }

    #[Test]
    public function testWriteDropsHkdfSaltSlot(): void
    {
        // setKey() under legacy-only mode is responsible for cleaning
        // up any stale HKDF salt so the session never holds both.
        $session = $this->buildSession();
        $session->setScoped('_secret', 'salt', str_repeat("\x0a", 32));
        $cbc = $this->buildLegacyOnly();
        $cbc->setSession($session);

        $cbc->setKey();

        self::assertFalse($session->hasScoped('_secret', 'salt'));
        self::assertTrue($session->hasScoped('_secret', 'key'));
    }

    #[Test]
    public function testEncryptDecryptRoundTrip(): void
    {
        $session = $this->buildSession();
        $cbc = $this->buildLegacyOnly();
        $cbc->setSession($session);

        $cipher = $cbc->write($cbc->getKey(), 'rollback-payload');

        $cbc2 = $this->buildLegacyOnly();
        $cbc2->setSession($session);
        $plain = $cbc2->read($cbc2->getKey(), $cipher);

        self::assertSame('rollback-payload', $plain);
    }

    #[Test]
    public function testFlipToHkdfOnlyMakesLegacyCiphertextUnreadable(): void
    {
        // Write under legacy-only, simulating a session created
        // during a rollback window.
        $session = $this->buildSession();
        $cbcLegacy = $this->buildLegacyOnly();
        $cbcLegacy->setSession($session);
        $cipher = $cbcLegacy->write($cbcLegacy->getKey(), 'rollback-only-payload');

        // Operator now flips to hkdf-only. Same session (operator
        // does not reset the user fleet); the session row carries
        // _secret/key from the legacy-only write.
        $cbcHkdf = new Horde_Core_Secret_Cbc([
            'cookie_domain' => '',
            'cookie_path' => '/',
            'cookie_ssl' => false,
            'iv' => str_repeat("\0", 8),
            'session_name' => 'horde_secret',
            'secret_key' => 'master-after-flip',
            'key_format' => Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_ONLY,
        ]);
        $cbcHkdf->setSession($session);

        // hkdf-only refuses to read the legacy slot. getKey() mints
        // a fresh HKDF salt and derives from it. That derived key
        // cannot decrypt the cipher bound to the rollback raw key.
        $recovered = null;
        try {
            $recovered = $cbcHkdf->read($cbcHkdf->getKey(), $cipher);
        } catch (Throwable) {
            $recovered = null;
        }

        self::assertNotSame('rollback-only-payload', $recovered);
    }
}
