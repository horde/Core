<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Secret;

use Horde\Core\Secret\HkdfInfo;
use Horde\Core\Session\HordeSession;
use Horde\Exception\HordeRuntimeException;
use Horde\SessionHandler\SessionId;
use Horde_Core_Secret_Cbc;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pin the HKDF-SHA256 derivation used by Horde_Core_Secret_Cbc for
 * per-session Blowfish key material.
 *
 * Construction: HKDF(sha256, ikm = configuredSecretKey . salt,
 * salt = session_id(), info = SESSION_BLOWFISH_CBC_V1, length = 56).
 *
 * These tests assert:
 *  - deterministic output given the same inputs
 *  - distinct outputs across distinct per-session salts
 *  - rotation: setKey() rotates the salt, derived key changes
 *  - rotation: session_id() changes, derived key changes
 *  - threat-model: an attacker holding only the row (salt +
 *    ciphertext) cannot decrypt without the master secret and the
 *    session id
 *  - bootstrap failure: empty configuredSecretKey is rejected
 */
#[CoversClass(Horde_Core_Secret_Cbc::class)]
#[CoversClass(HkdfInfo::class)]
class CbcHkdfTest extends TestCase
{
    private const MASTER = 'master-secret-do-not-leak';

    /** A stable session id to keep tests deterministic across runs. */
    private const SID = 'cbc-hkdf-test-sid';

    protected function setUp(): void
    {
        // Pin the PHP session id so deriveKey()'s call to session_id()
        // returns a predictable value. CLI lets us set it directly.
        session_id(self::SID);

        // Wipe cookie state that the parent Horde_Secret class reads.
        unset($_COOKIE['horde_secret_key'], $_COOKIE['horde_secret']);
    }

    protected function tearDown(): void
    {
        // Reset session_id and cookies so later tests in the suite
        // don't inherit our state. session_id('') is the documented
        // way to clear it in CLI.
        session_id('');
        unset($_COOKIE['horde_secret_key'], $_COOKIE['horde_secret']);
    }

    private function buildCbc(string $master = self::MASTER): Horde_Core_Secret_Cbc
    {
        return new Horde_Core_Secret_Cbc([
            'cookie_domain' => '',
            'cookie_path' => '/',
            'cookie_ssl' => false,
            'iv' => str_repeat("\0", 8),
            'session_name' => 'horde_secret',
            'secret_key' => $master,
            'key_format' => Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_WITH_LEGACY_FALLBACK,
        ]);
    }

    private function buildSessionWithSalt(string $salt): HordeSession
    {
        $session = new HordeSession(new SessionId(self::SID), []);
        $session->setScoped('_secret', 'salt', $salt);
        return $session;
    }

    #[Test]
    public function testDerivationIsDeterministicForSameInputs(): void
    {
        $salt = str_repeat("\x01", 32);

        $sessionA = $this->buildSessionWithSalt($salt);
        $cbcA = $this->buildCbc();
        $cbcA->setSession($sessionA);

        $sessionB = $this->buildSessionWithSalt($salt);
        $cbcB = $this->buildCbc();
        $cbcB->setSession($sessionB);

        self::assertSame($cbcA->getKey(), $cbcB->getKey());
        // Pin the length expected by Blowfish::cbc.
        self::assertSame(56, strlen($cbcA->getKey()));
    }

    #[Test]
    public function testDifferentSaltsProduceDifferentKeys(): void
    {
        $cbcA = $this->buildCbc();
        $cbcA->setSession($this->buildSessionWithSalt(str_repeat("\x01", 32)));

        $cbcB = $this->buildCbc();
        $cbcB->setSession($this->buildSessionWithSalt(str_repeat("\x02", 32)));

        self::assertNotSame($cbcA->getKey(), $cbcB->getKey());
    }

    #[Test]
    public function testDifferentMasterSecretsProduceDifferentKeys(): void
    {
        $salt = str_repeat("\x07", 32);

        $cbcA = $this->buildCbc('master-A');
        $cbcA->setSession($this->buildSessionWithSalt($salt));

        $cbcB = $this->buildCbc('master-B');
        $cbcB->setSession($this->buildSessionWithSalt($salt));

        self::assertNotSame($cbcA->getKey(), $cbcB->getKey());
    }

    #[Test]
    public function testSaltRotationChangesDerivedKey(): void
    {
        $session = $this->buildSessionWithSalt(str_repeat("\x03", 32));
        $cbc = $this->buildCbc();
        $cbc->setSession($session);

        $before = $cbc->getKey();
        $cbc->setKey();  // rotates the salt slot
        $after = $cbc->getKey();

        self::assertNotSame($before, $after);
    }

    #[Test]
    public function testSessionIdRotationChangesDerivedKey(): void
    {
        $salt = str_repeat("\x04", 32);
        $session = $this->buildSessionWithSalt($salt);
        $cbc = $this->buildCbc();
        $cbc->setSession($session);

        session_id('id-before');
        // Force re-derivation by invalidating the cache via setSession.
        $cbc->setSession($session);
        $before = $cbc->getKey();

        session_id('id-after');
        $cbc->setSession($session);
        $after = $cbc->getKey();

        self::assertNotSame($before, $after);
    }

    #[Test]
    public function testRowAloneIsUndecryptableWithoutMasterSecret(): void
    {
        // Encrypt under the right inputs.
        $salt = str_repeat("\x05", 32);
        $session = $this->buildSessionWithSalt($salt);
        $cbc = $this->buildCbc(self::MASTER);
        $cbc->setSession($session);
        $cipher = $cbc->write($cbc->getKey(), 'protected-payload');

        // Attacker has the row only: salt + ciphertext. They do NOT
        // have the master secret. They guess a different one.
        $attackerSession = $this->buildSessionWithSalt($salt);
        $attackerCbc = $this->buildCbc('attacker-guess');
        $attackerCbc->setSession($attackerSession);

        // The decrypt under a wrong-key derivation either throws
        // (PKCS#7 padding rejected) or returns bytes that are not
        // the plaintext. Either outcome proves the property.
        $recovered = null;
        try {
            $recovered = $attackerCbc->read($attackerCbc->getKey(), $cipher);
        } catch (\Throwable) {
            // Decrypt failed loudly under the wrong key.
            $recovered = null;
        }

        self::assertNotSame('protected-payload', $recovered);
    }

    #[Test]
    public function testRowAloneIsUndecryptableWithoutSessionId(): void
    {
        $salt = str_repeat("\x06", 32);
        $session = $this->buildSessionWithSalt($salt);
        $cbc = $this->buildCbc();
        $cbc->setSession($session);

        session_id('original-sid');
        $cbc->setSession($session);
        $cipher = $cbc->write($cbc->getKey(), 'protected-payload');

        // Attacker has salt + ciphertext + master secret but a
        // different session id (or none).
        session_id('attacker-sid');
        $attackerCbc = $this->buildCbc();
        $attackerCbc->setSession($session);

        $recovered = null;
        try {
            $recovered = $attackerCbc->read($attackerCbc->getKey(), $cipher);
        } catch (\Throwable) {
            $recovered = null;
        }

        self::assertNotSame('protected-payload', $recovered);
    }

    #[Test]
    public function testEmptyMasterSecretRejectedForHkdfFormats(): void
    {
        $this->expectException(HordeRuntimeException::class);
        $this->expectExceptionMessage('non-empty');

        new Horde_Core_Secret_Cbc([
            'cookie_domain' => '',
            'cookie_path' => '/',
            'cookie_ssl' => false,
            'iv' => str_repeat("\0", 8),
            'session_name' => 'horde_secret',
            'secret_key' => '',
            'key_format' => Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_ONLY,
        ]);
    }

    #[Test]
    public function testEmptyMasterSecretAcceptedForLegacyOnly(): void
    {
        // legacy-only does not use HKDF, so an empty master secret
        // is fine (the cipher IV comes from the separate 'iv' param).
        $cbc = new Horde_Core_Secret_Cbc([
            'cookie_domain' => '',
            'cookie_path' => '/',
            'cookie_ssl' => false,
            'iv' => str_repeat("\0", 8),
            'session_name' => 'horde_secret',
            'secret_key' => '',
            'key_format' => Horde_Core_Secret_Cbc::KEY_FORMAT_LEGACY_ONLY,
        ]);

        self::assertInstanceOf(Horde_Core_Secret_Cbc::class, $cbc);
    }

    #[Test]
    public function testInvalidKeyFormatRejected(): void
    {
        $this->expectException(HordeRuntimeException::class);
        $this->expectExceptionMessage('invalid key_format');

        new Horde_Core_Secret_Cbc([
            'cookie_domain' => '',
            'cookie_path' => '/',
            'cookie_ssl' => false,
            'iv' => str_repeat("\0", 8),
            'session_name' => 'horde_secret',
            'secret_key' => 'whatever',
            'key_format' => 'plaintext',
        ]);
    }

    #[Test]
    public function testSetKeyWritesSaltSlot(): void
    {
        $session = new HordeSession(new SessionId(self::SID), []);
        $cbc = $this->buildCbc();
        $cbc->setSession($session);

        $cbc->setKey();

        self::assertTrue($session->hasScoped('_secret', 'salt'));
        $salt = $session->getScoped('_secret', 'salt');
        self::assertIsString($salt);
        self::assertSame(32, strlen($salt));
    }

    #[Test]
    public function testClearKeyRemovesSaltSlot(): void
    {
        $session = $this->buildSessionWithSalt(str_repeat("\x08", 32));
        $cbc = $this->buildCbc();
        $cbc->setSession($session);

        $cbc->clearKey();

        self::assertFalse($session->hasScoped('_secret', 'salt'));
    }

    #[Test]
    public function testGetKeyOnFreshSessionMintsSalt(): void
    {
        // No salt slot pre-populated; getKey() must mint one via setKey().
        $session = new HordeSession(new SessionId(self::SID), []);
        $cbc = $this->buildCbc();
        $cbc->setSession($session);

        $key = $cbc->getKey();

        self::assertNotSame('', $key);
        self::assertSame(56, strlen($key));
        self::assertTrue($session->hasScoped('_secret', 'salt'));
    }

    #[Test]
    public function testEncryptDecryptRoundTripUnderHkdf(): void
    {
        $session = new HordeSession(new SessionId(self::SID), []);
        $cbc = $this->buildCbc();
        $cbc->setSession($session);

        $cipher = $cbc->write($cbc->getKey(), 'integration-payload');

        // Read back through a fresh Cbc that resolves the same session.
        $cbc2 = $this->buildCbc();
        $cbc2->setSession($session);
        $plain = $cbc2->read($cbc2->getKey(), $cipher);

        self::assertSame('integration-payload', $plain);
    }
}
