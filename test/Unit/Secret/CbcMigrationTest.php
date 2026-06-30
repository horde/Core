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
 * Pin the shape-2 → shape-3 migration path in Horde_Core_Secret_Cbc.
 *
 * Three shapes exist in the wild after the HKDF deployment:
 *  - shape-1: pre-PR-#174, key in cookie only (handled by parent).
 *  - shape-2: PR-#174, raw key at `_secret/key` in the session.
 *  - shape-3: HKDF, salt at `_secret/salt`, key derived per request.
 *
 * These tests cover the shape-2 → shape-3 transition because shape-2
 * is the dominant pre-HKDF state in the wild. They also pin the
 * operator-controlled `key_format` switch that lets operators select
 * one of three modes:
 *
 *  - `hkdf-only`: refuse shape-2 reads; new writes are shape-3.
 *  - `hkdf-with-legacy-fallback` (default): read either shape,
 *    write shape-3, migrate shape-2 to shape-3 on rotation.
 *  - `legacy-only`: refuse shape-3 reads; new writes are shape-2.
 *    Operator rollback path for a severe HKDF-side bug. Covered in
 *    {@see CbcLegacyOnlyTest}.
 */
#[CoversClass(Horde_Core_Secret_Cbc::class)]
class CbcMigrationTest extends TestCase
{
    private const MASTER = 'master-for-migration-tests';
    private const SID = 'cbc-migration-sid';

    protected function setUp(): void
    {
        session_id(self::SID);
        unset($_COOKIE['horde_secret_key'], $_COOKIE['horde_secret']);
    }

    protected function tearDown(): void
    {
        session_id('');
        unset($_COOKIE['horde_secret_key'], $_COOKIE['horde_secret']);
    }

    private function buildCbc(string $keyFormat = Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_WITH_LEGACY_FALLBACK): Horde_Core_Secret_Cbc
    {
        return new Horde_Core_Secret_Cbc([
            'cookie_domain' => '',
            'cookie_path' => '/',
            'cookie_ssl' => false,
            'iv' => str_repeat("\0", 8),
            'session_name' => 'horde_secret',
            'secret_key' => self::MASTER,
            'key_format' => $keyFormat,
        ]);
    }

    /**
     * Build a session in shape-2: raw key at `_secret/key`, no salt slot.
     * This is what PR-#174-era code wrote.
     */
    private function buildShape2Session(string $legacyKey): HordeSession
    {
        $session = new HordeSession(new SessionId(self::SID), []);
        $session->setScoped('_secret', 'key', $legacyKey);
        return $session;
    }

    #[Test]
    public function testShape2SessionReadableWhenLegacyShapeAllowed(): void
    {
        $session = $this->buildShape2Session('shape-2-raw-key');
        $cbc = $this->buildCbc(Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_WITH_LEGACY_FALLBACK);
        $cbc->setSession($session);

        self::assertSame('shape-2-raw-key', $cbc->getKey());
    }

    #[Test]
    public function testShape2SessionMintsFreshSaltWhenLegacyShapeDisabled(): void
    {
        // With legacy shape support off, getKey() must NOT return the
        // legacy raw key. Instead it falls through to setKey() which
        // mints a fresh HKDF salt; the derived key is unrelated to
        // the legacy raw key. Existing ciphertext bound to the
        // legacy raw key becomes unrecoverable from this point on.
        $session = $this->buildShape2Session('shape-2-raw-key');
        $cbc = $this->buildCbc(Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_ONLY);
        $cbc->setSession($session);

        $derived = $cbc->getKey();

        self::assertNotSame('shape-2-raw-key', $derived);
        self::assertSame(56, strlen($derived));
        self::assertTrue($session->hasScoped('_secret', 'salt'));
    }

    #[Test]
    public function testSetKeyOnShape2SessionMigratesToShape3(): void
    {
        // setKey() always rotates to shape-3 regardless of how the
        // session arrived: it writes a salt slot and removes the
        // legacy key slot. The session is now in canonical HKDF
        // shape and behaves identically to a freshly-minted one.
        $session = $this->buildShape2Session('shape-2-raw-key');
        $cbc = $this->buildCbc(Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_WITH_LEGACY_FALLBACK);
        $cbc->setSession($session);

        $newKey = $cbc->setKey();

        self::assertNotSame('shape-2-raw-key', $newKey);
        self::assertFalse($session->hasScoped('_secret', 'key'));
        self::assertTrue($session->hasScoped('_secret', 'salt'));
    }

    #[Test]
    public function testShape2EncryptedDataReadableWithLegacyShapeOn(): void
    {
        // The strongest migration assertion: data encrypted under the
        // shape-2 raw key must still be decryptable through the same
        // Cbc as long as the legacy slot stays in place. This is
        // exactly the property an operator relies on when they
        // upgrade to HKDF code: their existing sessions stay readable.
        $legacyKey = 'legacy-raw-key-' . str_repeat('Q', 40);
        $session = $this->buildShape2Session($legacyKey);
        $cbc = $this->buildCbc(Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_WITH_LEGACY_FALLBACK);
        $cbc->setSession($session);

        // Encrypt while shape-2 (uses the raw key from the slot).
        $cipher = $cbc->write($cbc->getKey(), 'preserved-payload');

        // Re-resolve through a fresh Cbc that sees the same session.
        $cbc2 = $this->buildCbc(Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_WITH_LEGACY_FALLBACK);
        $cbc2->setSession($session);
        $plain = $cbc2->read($cbc2->getKey(), $cipher);

        self::assertSame('preserved-payload', $plain);
    }

    #[Test]
    public function testShape2EncryptedDataUnrecoverableWithLegacyShapeOff(): void
    {
        $legacyKey = 'legacy-raw-key-' . str_repeat('Q', 40);
        $session = $this->buildShape2Session($legacyKey);

        // Encrypt under legacy shape (cbc has legacy reading enabled
        // for this initial write so it reaches the legacy slot).
        $cbcLegacyOn = $this->buildCbc(Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_WITH_LEGACY_FALLBACK);
        $cbcLegacyOn->setSession($session);
        $cipher = $cbcLegacyOn->write($cbcLegacyOn->getKey(), 'unreachable-payload');

        // Operator now disables legacy shape support. Build a fresh
        // session view, but feed it the same payload (a real session
        // load from the backend). The same legacy slot exists in the
        // row but the new Cbc refuses to read it.
        $session2 = $this->buildShape2Session($legacyKey);
        $cbcLegacyOff = $this->buildCbc(Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_ONLY);
        $cbcLegacyOff->setSession($session2);

        // getKey() now returns a freshly-derived HKDF key, unrelated
        // to the legacy key the ciphertext was bound to.
        $recovered = null;
        try {
            $recovered = $cbcLegacyOff->read($cbcLegacyOff->getKey(), $cipher);
        } catch (Throwable) {
            $recovered = null;
        }

        self::assertNotSame('unreachable-payload', $recovered);
    }

    #[Test]
    public function testClearKeyRemovesBothShapesSlots(): void
    {
        $session = $this->buildShape2Session('legacy-key');
        $session->setScoped('_secret', 'salt', str_repeat("\x01", 32));
        $cbc = $this->buildCbc();
        $cbc->setSession($session);

        $cbc->clearKey();

        self::assertFalse($session->hasScoped('_secret', 'key'));
        self::assertFalse($session->hasScoped('_secret', 'salt'));
    }

    #[Test]
    public function testGetKeyOnSessionWithBothSlotsPrefersHkdf(): void
    {
        // Defensive: a session with both shape-2 and shape-3 slots
        // (e.g. an interrupted migration that wrote a salt but didn't
        // get around to dropping the legacy key) reads as shape-3.
        // Once HKDF has been chosen for this session, the legacy
        // slot is ignored. setKey() at the next rotation drops it.
        $session = new HordeSession(new SessionId(self::SID), []);
        $session->setScoped('_secret', 'key', 'legacy-loses');
        $session->setScoped('_secret', 'salt', str_repeat("\x02", 32));

        $cbc = $this->buildCbc(Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_WITH_LEGACY_FALLBACK);
        $cbc->setSession($session);

        // The returned key is the HKDF derivation, not the legacy
        // raw value. We don't predict the exact derivation here,
        // but it must not be the legacy value, and must be 56
        // bytes (Blowfish max).
        $key = $cbc->getKey();
        self::assertNotSame('legacy-loses', $key);
        self::assertSame(56, strlen($key));
    }
}
