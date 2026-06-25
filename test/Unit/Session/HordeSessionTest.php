<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Session;

use Horde\Core\Session\EncryptedValuesInterface;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionMetaInterface;
use Horde\SessionHandler\Exception\SessionException;
use Horde\SessionHandler\Session;
use Horde\SessionHandler\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Horde_Pack;

#[CoversClass(HordeSession::class)]
class HordeSessionTest extends TestCase
{
    private function createSession(array $data = []): HordeSession
    {
        return new HordeSession(new SessionId('test-id'), $data);
    }

    // ---------------------------------------------------------------
    // Basic identity
    // ---------------------------------------------------------------

    #[Test]
    public function testImplementsSession(): void
    {
        $session = $this->createSession();
        self::assertInstanceOf(Session::class, $session);
    }

    #[Test]
    public function testImplementsSessionMetaInterface(): void
    {
        $session = $this->createSession();
        self::assertInstanceOf(SessionMetaInterface::class, $session);
    }

    #[Test]
    public function testImplementsEncryptedValuesInterface(): void
    {
        $session = $this->createSession();
        self::assertInstanceOf(EncryptedValuesInterface::class, $session);
    }

    // ---------------------------------------------------------------
    // Two-level scoped access
    // ---------------------------------------------------------------

    #[Test]
    public function testGetScopedReturnsNullForMissing(): void
    {
        $session = $this->createSession();
        self::assertNull($session->getScoped('horde', 'missing'));
    }

    #[Test]
    public function testSetAndGetScoped(): void
    {
        $session = $this->createSession();
        $session->setScoped('imp', 'mailbox', 'INBOX');
        self::assertSame('INBOX', $session->getScoped('imp', 'mailbox'));
    }

    #[Test]
    public function testHasScopedReturnsFalseForMissing(): void
    {
        $session = $this->createSession();
        self::assertFalse($session->hasScoped('imp', 'mailbox'));
    }

    #[Test]
    public function testHasScopedReturnsTrueAfterSet(): void
    {
        $session = $this->createSession();
        $session->setScoped('imp', 'mailbox', 'INBOX');
        self::assertTrue($session->hasScoped('imp', 'mailbox'));
    }

    #[Test]
    public function testRemoveScoped(): void
    {
        $session = $this->createSession();
        $session->setScoped('imp', 'mailbox', 'INBOX');
        $session->removeScoped('imp', 'mailbox');
        self::assertNull($session->getScoped('imp', 'mailbox'));
        self::assertFalse($session->hasScoped('imp', 'mailbox'));
    }

    #[Test]
    public function testKeysForApp(): void
    {
        $session = $this->createSession([
            'imp' => ['mailbox' => 'INBOX', 'folder' => 'Sent'],
            'horde' => ['pref' => 'val'],
        ]);

        $keys = $session->keysForApp('imp');
        sort($keys);
        self::assertSame(['folder', 'mailbox'], $keys);
    }

    #[Test]
    public function testKeysForAppReturnsEmptyForMissingApp(): void
    {
        $session = $this->createSession();
        self::assertSame([], $session->keysForApp('nonexistent'));
    }

    // ---------------------------------------------------------------
    // Wire compatibility: toPayload preserves two-level structure
    // ---------------------------------------------------------------

    #[Test]
    public function testToPayloadPreservesTwoLevelStructure(): void
    {
        $session = $this->createSession();
        $session->setScoped('horde', 'auth/userId', 'alice');
        $session->setScoped('imp', 'mailbox', 'INBOX');

        $payload = $session->toPayload();

        // String values go on the wire prefixed with NOT_SERIALIZED so the
        // legacy Horde_Session can still distinguish them from packed shapes.
        self::assertSame("\0alice", $payload['horde']['auth/userId']);
        self::assertSame("\0INBOX", $payload['imp']['mailbox']);

        // Round-trip via the public API recovers the original strings.
        self::assertSame('alice', $session->getScoped('horde', 'auth/userId'));
        self::assertSame('INBOX', $session->getScoped('imp', 'mailbox'));
    }

    #[Test]
    public function testRestoreFromNativeSessionData(): void
    {
        // Simulate data as it comes from session_decode() / $_SESSION
        $nativeData = [
            '_b' => 1700000000,
            'horde' => [
                'auth/userId' => 'alice',
                'auth/browser' => 'Firefox',
                'auth/remoteAddr' => '10.0.0.1',
                'auth/timestamp' => 1700000000,
                'auth_app/imp' => 'cred1',
                'auth_app/kronolith' => 'cred2',
            ],
            'imp' => [
                'mailbox' => 'INBOX',
            ],
        ];

        $session = $this->createSession($nativeData);

        // Values are accessible directly
        self::assertSame('alice', $session->getScoped('horde', 'auth/userId'));
        self::assertSame('INBOX', $session->getScoped('imp', 'mailbox'));

        // toPayload round-trips
        self::assertSame($nativeData, $session->toPayload());
    }

    // ---------------------------------------------------------------
    // SessionMetaInterface
    // ---------------------------------------------------------------

    #[Test]
    public function testGetAuthenticatedUser(): void
    {
        $session = $this->createSession([
            'horde' => ['auth/userId' => 'alice'],
        ]);
        self::assertSame('alice', $session->getAuthenticatedUser());
    }

    #[Test]
    public function testGetAuthenticatedUserReturnsNullWhenMissing(): void
    {
        $session = $this->createSession();
        self::assertNull($session->getAuthenticatedUser());
    }

    #[Test]
    public function testGetAuthId(): void
    {
        $session = $this->createSession([
            'horde' => ['auth/authId' => 'alice@example.com'],
        ]);
        self::assertSame('alice@example.com', $session->getAuthId());
    }

    #[Test]
    public function testGetBrowserFingerprint(): void
    {
        $session = $this->createSession([
            'horde' => ['auth/browser' => 'Mozilla/5.0'],
        ]);
        self::assertSame('Mozilla/5.0', $session->getBrowserFingerprint());
    }

    #[Test]
    public function testGetRemoteAddress(): void
    {
        $session = $this->createSession([
            'horde' => ['auth/remoteAddr' => '192.168.1.1'],
        ]);
        self::assertSame('192.168.1.1', $session->getRemoteAddress());
    }

    #[Test]
    public function testGetAuthTimestamp(): void
    {
        $session = $this->createSession([
            'horde' => ['auth/timestamp' => 1700000000],
        ]);
        $ts = $session->getAuthTimestamp();
        self::assertNotNull($ts);
        self::assertSame(1700000000, $ts->getTimestamp());
    }

    #[Test]
    public function testGetAuthTimestampReturnsNullForNonInt(): void
    {
        $session = $this->createSession([
            'horde' => ['auth/timestamp' => 'not-an-int'],
        ]);
        self::assertNull($session->getAuthTimestamp());
    }

    #[Test]
    public function testGetSessionBegin(): void
    {
        $session = $this->createSession(['_b' => 1700000000]);
        $ts = $session->getSessionBegin();
        self::assertNotNull($ts);
        self::assertSame(1700000000, $ts->getTimestamp());
    }

    #[Test]
    public function testSetSessionBeginRoundTrips(): void
    {
        $session = $this->createSession([]);
        $session->setSessionBegin(1700000123);
        $ts = $session->getSessionBegin();
        self::assertNotNull($ts);
        self::assertSame(1700000123, $ts->getTimestamp());
        self::assertTrue($session->isDirty(), 'setSessionBegin must mark session dirty');
    }

    #[Test]
    public function testGetSessionBeginIgnoresLegacyArrayShape(): void
    {
        // The pre-fix legacy shim wrote the begin slot via
        // setScoped(BEGIN, '', $ts) which produces $data['_b'][''] = $ts.
        // getSessionBegin only honours the canonical top-level int shape;
        // sessions in the legacy shape return null from this getter
        // until they are rewritten by setSessionBegin during the next
        // initialiseTimestamps pass.
        $session = $this->createSession(['_b' => ['' => 1700000000]]);
        self::assertNull($session->getSessionBegin());
    }

    #[Test]
    public function testGetRegenerationDeadlineRoundTrips(): void
    {
        $session = $this->createSession([]);
        self::assertNull($session->getRegenerationDeadline());

        $session->setRegenerationDeadline(1700001234);
        self::assertSame(1700001234, $session->getRegenerationDeadline());
        self::assertTrue($session->isDirty());
    }

    #[Test]
    public function testGetRegenerationDeadlineFromInitialPayload(): void
    {
        $session = $this->createSession(['_r' => 1700001234]);
        self::assertSame(1700001234, $session->getRegenerationDeadline());
    }

    #[Test]
    public function testGetRegenerationDeadlineIgnoresLegacyArrayShape(): void
    {
        // Pre-fix writers used setScoped(REGENERATE_KEY, '', $ts) which
        // produced $data['_r']['']. The reader was internally consistent
        // (also via getScoped) but the shape disagreed with how _b is
        // stored. Sessions still on disk in the legacy shape return null
        // from this getter until the next setRegenerationDeadline pass.
        $session = $this->createSession(['_r' => ['' => 1700001234]]);
        self::assertNull($session->getRegenerationDeadline());
    }

    #[Test]
    public function testGetAuthenticatedApps(): void
    {
        $session = $this->createSession([
            'horde' => [
                'auth_app/imp' => 'cred1',
                'auth_app/kronolith' => 'cred2',
                'auth/userId' => 'alice',
            ],
        ]);

        $apps = $session->getAuthenticatedApps();
        sort($apps);
        self::assertSame(['imp', 'kronolith'], $apps);
    }

    #[Test]
    public function testGetAuthenticatedAppsReturnsEmptyWhenNoAuth(): void
    {
        $session = $this->createSession();
        self::assertSame([], $session->getAuthenticatedApps());
    }

    // ---------------------------------------------------------------
    // EncryptedValuesInterface
    // ---------------------------------------------------------------

    #[Test]
    public function testSetEncryptedThrowsWithoutEncryptor(): void
    {
        $session = $this->createSession();

        $this->expectException(SessionException::class);
        $session->setEncrypted('horde', 'secret', 'value');
    }

    #[Test]
    public function testGetEncryptedReturnsRawWithoutDecryptor(): void
    {
        $session = $this->createSession([
            'horde' => ['secret' => 'raw-value'],
        ]);
        self::assertSame('raw-value', $session->getEncrypted('horde', 'secret'));
    }

    #[Test]
    public function testSetAndGetEncrypted(): void
    {
        $encryptor = fn(string $p): string => 'ENC:' . $p;
        $decryptor = fn(string $c): string => substr($c, 4);

        $session = new HordeSession(
            new SessionId('enc-test'),
            [],
            $encryptor,
            $decryptor,
        );

        $session->setEncrypted('horde', 'auth_app/imp', ['password' => 'secret']);

        // Raw value is encrypted
        $raw = $session->getScoped('horde', 'auth_app/imp');
        self::assertStringStartsWith('ENC:', $raw);

        // getEncrypted decrypts and unserializes
        $decrypted = $session->getEncrypted('horde', 'auth_app/imp');
        self::assertSame(['password' => 'secret'], $decrypted);
    }

    #[Test]
    public function testGetEncryptedReturnsNullWhenDecryptorThrowsBlowfishException(): void
    {
        // Simulates the imp #66 path: the stored ciphertext was encrypted
        // under a key the current decryptor no longer holds, so unpad()
        // throws "Invalid PKCS#7 padding byte". The session must fail
        // closed to null instead of letting the throwable escape and kill
        // the request.
        $writer = new HordeSession(
            new SessionId('enc-test'),
            [],
            fn(string $p): string => 'ENC:' . $p,
            fn(string $c): string => substr($c, 4),
        );
        $writer->setEncrypted('horde', 'auth_app/imp', ['password' => 'secret']);

        $brokenDecryptor = function (string $c): string {
            throw new \Horde\Crypt\Blowfish\EncryptionException(
                'Invalid PKCS#7 padding byte: 0x61',
            );
        };
        $reader = new HordeSession(
            new SessionId('enc-test'),
            $writer->toPayload(),
            fn(string $p): string => 'ENC:' . $p,
            $brokenDecryptor,
        );

        self::assertNull($reader->getEncrypted('horde', 'auth_app/imp'));
    }

    #[Test]
    public function testGetEncryptedReturnsNullOnArbitraryThrowableFromDecryptor(): void
    {
        // Defensive: any Throwable from the decryptor closure is contained,
        // not just the Blowfish-specific exception.
        $writer = new HordeSession(
            new SessionId('enc-test'),
            [],
            fn(string $p): string => 'ENC:' . $p,
            fn(string $c): string => substr($c, 4),
        );
        $writer->setEncrypted('horde', 'secret', 'value');

        $reader = new HordeSession(
            new SessionId('enc-test'),
            $writer->toPayload(),
            fn(string $p): string => 'ENC:' . $p,
            function (string $c): string {
                throw new \RuntimeException('decryptor blew up');
            },
        );

        self::assertNull($reader->getEncrypted('horde', 'secret'));
    }

    #[Test]
    public function testGetEncryptedReturnsDecryptedBytesWhenPackUnpackFails(): void
    {
        // Regression guard: a successful decrypt whose result is NOT
        // Horde_Pack-formatted must keep returning the raw decrypted
        // bytes (legacy wire format). The new outer Throwable catch
        // must not accidentally swallow this case to null.
        $writer = new HordeSession(
            new SessionId('enc-test'),
            [],
            fn(string $p): string => 'ENC:' . $p,
            fn(string $c): string => substr($c, 4),
        );
        $writer->setEncrypted('horde', 'secret', 'placeholder');

        // Decrypt succeeds and returns bytes that Horde_Pack will reject.
        $reader = new HordeSession(
            new SessionId('enc-test'),
            $writer->toPayload(),
            fn(string $p): string => 'ENC:' . $p,
            fn(string $c): string => 'not-a-pack-payload',
        );

        self::assertSame('not-a-pack-payload', $reader->getEncrypted('horde', 'secret'));
    }

    // ---------------------------------------------------------------
    // reEncryptAll() — rotation of the underlying encryption key
    // ---------------------------------------------------------------

    /**
     * Build a session whose closures read a key from a mutable
     * holder. Mutating the holder simulates a key rotation between
     * drain and refill phases of reEncryptAll().
     *
     * @return array{0: HordeSession, 1: \stdClass} The session and the
     *                                              key holder.
     */
    private function sessionWithMutableKey(string $initialKey = 'KEY1'): array
    {
        $holder = new \stdClass();
        $holder->key = $initialKey;
        // Encryption: tag the plaintext with the active key on write.
        $encryptor = static function (string $plaintext) use ($holder): string {
            return $holder->key . '|' . $plaintext;
        };
        // Decryption: only succeeds if the active key matches the tag.
        $decryptor = static function (string $ciphertext) use ($holder): string {
            $prefix = $holder->key . '|';
            if (!str_starts_with($ciphertext, $prefix)) {
                throw new \RuntimeException('wrong key');
            }
            return substr($ciphertext, strlen($prefix));
        };
        $session = new HordeSession(
            new SessionId('rotate-test'),
            [],
            $encryptor,
            $decryptor,
        );

        return [$session, $holder];
    }

    #[Test]
    public function testReEncryptAllRoundTripsPlaintextAcrossKeyRotation(): void
    {
        [$session, $holder] = $this->sessionWithMutableKey('KEY1');
        $session->setEncrypted('horde', 'auth_app/imp', ['password' => 'p1']);
        $session->setEncrypted('horde', 'auth_app/turba', 'p2');

        $rawBefore = $session->getScoped('horde', 'auth_app/imp');
        self::assertStringStartsWith('KEY1|', $rawBefore);

        $session->reEncryptAll(function () use ($holder) {
            $holder->key = 'KEY2';
        });

        // Ciphertext has been re-bound to the new key.
        $rawAfter = $session->getScoped('horde', 'auth_app/imp');
        self::assertStringStartsWith('KEY2|', $rawAfter);

        // Plaintext round-trips under the new key.
        self::assertSame(
            ['password' => 'p1'],
            $session->getEncrypted('horde', 'auth_app/imp'),
        );
        self::assertSame(
            'p2',
            $session->getEncrypted('horde', 'auth_app/turba'),
        );
    }

    #[Test]
    public function testReEncryptAllDropsUndecryptableSlots(): void
    {
        // Seed two slots under KEY1, then swap one out for ciphertext
        // bound to an unknown key. After reEncryptAll, the unrecoverable
        // slot is removed from both $data and the encryption map; the
        // healthy slot survives and is re-encrypted under KEY2.
        [$session, $holder] = $this->sessionWithMutableKey('KEY1');
        $session->setEncrypted('horde', 'good', 'value');
        $session->setEncrypted('horde', 'bad', 'placeholder');
        $session->setScoped('horde', 'bad', 'UNKNOWN|garbage');

        $session->reEncryptAll(function () use ($holder) {
            $holder->key = 'KEY2';
        });

        self::assertNull($session->getScoped('horde', 'bad'));
        self::assertFalse($session->isEncrypted('horde', 'bad'));
        self::assertTrue($session->isEncrypted('horde', 'good'));
        self::assertSame('value', $session->getEncrypted('horde', 'good'));
    }

    #[Test]
    public function testReEncryptAllRunsCallbackBetweenDecryptAndEncrypt(): void
    {
        // Pin the sequence: the callback must observe the OLD key (drain
        // already happened) and may install the NEW key (refill happens
        // after the callback returns).
        [$session, $holder] = $this->sessionWithMutableKey('KEY1');
        $session->setEncrypted('horde', 'slot', 'value');

        $observed = null;
        $session->reEncryptAll(function () use ($holder, &$observed) {
            $observed = $holder->key;
            $holder->key = 'KEY2';
        });

        self::assertSame('KEY1', $observed, 'callback runs with the old key in scope');
        self::assertStringStartsWith('KEY2|', $session->getScoped('horde', 'slot'));
    }

    #[Test]
    public function testReEncryptAllPropagatesCallbackExceptionWithoutReEncrypting(): void
    {
        [$session, $holder] = $this->sessionWithMutableKey('KEY1');
        $session->setEncrypted('horde', 'slot', 'value');
        $rawBefore = $session->getScoped('horde', 'slot');

        try {
            $session->reEncryptAll(function () use ($holder) {
                $holder->key = 'KEY2';
                throw new \LogicException('rotation aborted');
            });
            self::fail('expected exception');
        } catch (\LogicException $e) {
            self::assertSame('rotation aborted', $e->getMessage());
        }

        // The encrypted slot stays as it was. It would be re-readable
        // only if the key holder is rolled back to KEY1 (the caller's
        // responsibility); the session itself made no further mutation
        // after the callback threw.
        self::assertSame($rawBefore, $session->getScoped('horde', 'slot'));
    }

    #[Test]
    public function testReEncryptAllNoEncryptedSlotsStillRunsCallback(): void
    {
        [$session, $holder] = $this->sessionWithMutableKey('KEY1');
        $called = false;

        $session->reEncryptAll(function () use ($holder, &$called) {
            $holder->key = 'KEY2';
            $called = true;
        });

        self::assertTrue($called);
    }

    #[Test]
    public function testIsEncrypted(): void
    {
        $encryptor = fn(string $p): string => 'ENC:' . $p;
        $decryptor = fn(string $c): string => substr($c, 4);

        $session = new HordeSession(
            new SessionId('enc-test'),
            [],
            $encryptor,
            $decryptor,
        );

        $session->setEncrypted('horde', 'auth_app/imp', 'value');
        self::assertTrue($session->isEncrypted('horde', 'auth_app/imp'));
        self::assertFalse($session->isEncrypted('horde', 'auth/userId'));
    }

    #[Test]
    public function testEncryptionMapIsTwoLevel(): void
    {
        $encryptor = fn(string $p): string => 'ENC:' . $p;
        $decryptor = fn(string $c): string => substr($c, 4);

        $session = new HordeSession(
            new SessionId('map-test'),
            [],
            $encryptor,
            $decryptor,
        );

        $session->setEncrypted('horde', 'auth_app/imp', 'val1');
        $session->setEncrypted('imp', 'stored_password', 'val2');

        $map = $session->getEncryptionMap();

        // Map matches legacy $_SESSION['_e'] structure
        self::assertTrue($map['horde']['auth_app/imp']);
        self::assertTrue($map['imp']['stored_password']);
    }

    #[Test]
    public function testEncryptionMapInPayload(): void
    {
        $encryptor = fn(string $p): string => 'ENC:' . $p;
        $decryptor = fn(string $c): string => substr($c, 4);

        $session = new HordeSession(
            new SessionId('payload-test'),
            [],
            $encryptor,
            $decryptor,
        );

        $session->setEncrypted('horde', 'auth_app/imp', 'val');

        $payload = $session->toPayload();

        // Encryption map is stored at $_SESSION['_e'][$app][$name]
        self::assertTrue($payload['_e']['horde']['auth_app/imp']);
        // Encrypted value is stored at $_SESSION[$app][$name]
        self::assertStringStartsWith('ENC:', $payload['horde']['auth_app/imp']);
    }

    #[Test]
    public function testRemoveScopedClearsEncryptionMap(): void
    {
        $encryptor = fn(string $p): string => 'ENC:' . $p;
        $decryptor = fn(string $c): string => substr($c, 4);

        $session = new HordeSession(
            new SessionId('rm-test'),
            [],
            $encryptor,
            $decryptor,
        );

        $session->setEncrypted('horde', 'auth_app/imp', 'val');
        self::assertTrue($session->isEncrypted('horde', 'auth_app/imp'));

        $session->removeScoped('horde', 'auth_app/imp');
        self::assertFalse($session->isEncrypted('horde', 'auth_app/imp'));
    }

    // ---------------------------------------------------------------
    // Re-encryption
    // ---------------------------------------------------------------

    #[Test]
    public function testUpdateEncryptionCallbacks(): void
    {
        $oldEnc = fn(string $p): string => 'OLD:' . $p;
        $oldDec = fn(string $c): string => substr($c, 4);
        $newEnc = fn(string $p): string => 'NEW:' . $p;
        $newDec = fn(string $c): string => substr($c, 4);

        $session = new HordeSession(
            new SessionId('reenc-test'),
            [],
            $oldEnc,
            $oldDec,
        );

        $session->setEncrypted('horde', 'auth_app/imp', 'credentials');

        // Before: encrypted with old key
        $rawBefore = $session->getScoped('horde', 'auth_app/imp');
        self::assertStringStartsWith('OLD:', $rawBefore);

        // Re-encrypt
        $session->updateEncryptionCallbacks($newEnc, $newDec);

        // After: encrypted with new key
        $rawAfter = $session->getScoped('horde', 'auth_app/imp');
        self::assertStringStartsWith('NEW:', $rawAfter);

        // Decryption still works
        $decrypted = $session->getEncrypted('horde', 'auth_app/imp');
        self::assertSame('credentials', $decrypted);
    }

    // ---------------------------------------------------------------
    // Wire compatibility: restore from legacy payload
    // ---------------------------------------------------------------

    #[Test]
    public function testRestoreFromLegacyPayloadWithEncryptionMap(): void
    {
        $decryptor = fn(string $c): string => substr($c, 4);

        // Simulate a legacy session payload with encryption map. The legacy
        // Horde_Session writes encrypt(Horde_Pack::pack($value)) for ENCRYPT
        // slots; reproduce that exact wire shape here.
        $pack = new Horde_Pack();
        $packed = $pack->pack('imp-password', ['compress' => 0]);
        $legacyPayload = [
            '_b' => 1700000000,
            '_e' => [
                'horde' => [
                    'auth_app/imp' => true,
                ],
            ],
            'horde' => [
                'auth/userId' => 'alice',
                'auth/timestamp' => 1700000000,
                'auth_app/imp' => 'ENC:' . $packed,
            ],
        ];

        $session = new HordeSession(
            new SessionId('legacy-test'),
            $legacyPayload,
            null,
            $decryptor,
        );

        // Meta works
        self::assertSame('alice', $session->getAuthenticatedUser());
        self::assertSame(1700000000, $session->getAuthTimestamp()->getTimestamp());
        self::assertSame(1700000000, $session->getSessionBegin()->getTimestamp());
        self::assertSame(['imp'], $session->getAuthenticatedApps());

        // Encryption map is detected
        self::assertTrue($session->isEncrypted('horde', 'auth_app/imp'));

        // Encrypted value is decryptable
        self::assertSame('imp-password', $session->getEncrypted('horde', 'auth_app/imp'));

        // Payload round-trips
        self::assertSame($legacyPayload, $session->toPayload());
    }

    // ---------------------------------------------------------------
    // Dirty tracking
    // ---------------------------------------------------------------

    #[Test]
    public function testNotDirtyAfterRestore(): void
    {
        $session = $this->createSession(['_b' => 1700000000]);
        self::assertFalse($session->isDirty());
    }

    #[Test]
    public function testDirtyAfterSetScoped(): void
    {
        $session = $this->createSession();
        $session->setScoped('horde', 'key', 'val');
        self::assertTrue($session->isDirty());
    }

    #[Test]
    public function testDirtyAfterRemoveScoped(): void
    {
        $session = $this->createSession([
            'horde' => ['key' => 'val'],
        ]);
        $session->removeScoped('horde', 'key');
        self::assertTrue($session->isDirty());
    }

    // ---------------------------------------------------------------
    // Lifecycle intent flags (scheduleRegeneration / markDestroyed)
    // ---------------------------------------------------------------

    #[Test]
    public function testFlagsDefaultToFalse(): void
    {
        $session = $this->createSession();
        self::assertFalse($session->shouldRegenerate());
        self::assertFalse($session->isDestroyed());
    }

    #[Test]
    public function testScheduleRegenerationSetsFlag(): void
    {
        $session = $this->createSession();
        $session->scheduleRegeneration();
        self::assertTrue($session->shouldRegenerate());
    }

    #[Test]
    public function testScheduleRegenerationIsIdempotent(): void
    {
        $session = $this->createSession();
        $session->scheduleRegeneration();
        $session->scheduleRegeneration();
        self::assertTrue($session->shouldRegenerate());
    }

    #[Test]
    public function testMarkDestroyedSetsFlag(): void
    {
        $session = $this->createSession();
        $session->markDestroyed();
        self::assertTrue($session->isDestroyed());
    }

    #[Test]
    public function testMarkDestroyedIsIdempotent(): void
    {
        $session = $this->createSession();
        $session->markDestroyed();
        $session->markDestroyed();
        self::assertTrue($session->isDestroyed());
    }

    #[Test]
    public function testFlagsAreIndependent(): void
    {
        $session = $this->createSession();
        $session->scheduleRegeneration();
        self::assertTrue($session->shouldRegenerate());
        self::assertFalse($session->isDestroyed());

        $other = $this->createSession();
        $other->markDestroyed();
        self::assertTrue($other->isDestroyed());
        self::assertFalse($other->shouldRegenerate());
    }

    #[Test]
    public function testBothFlagsCanBeSet(): void
    {
        $session = $this->createSession();
        $session->scheduleRegeneration();
        $session->markDestroyed();
        self::assertTrue($session->shouldRegenerate());
        self::assertTrue($session->isDestroyed());
    }

    #[Test]
    public function testFlagsNotInPayload(): void
    {
        // Regression sentinel: the flags must not leak into the
        // serialised payload that hits the backend. They are
        // request-scoped runtime state.
        $session = $this->createSession();
        $session->scheduleRegeneration();
        $session->markDestroyed();

        $payload = $session->toPayload();
        $flat = json_encode($payload);
        self::assertNotFalse($flat);
        self::assertStringNotContainsString('regenerationScheduled', $flat);
        self::assertStringNotContainsString('destroyed', $flat);
        self::assertStringNotContainsString('shouldRegenerate', $flat);
    }

    #[Test]
    public function testRestoredSessionStartsWithFlagsFalse(): void
    {
        // A session restored from a payload that happens to contain
        // misleading keys must not pick up the flags. They are private
        // properties of the runtime instance, not session data.
        $session = $this->createSession([
            'regenerationScheduled' => true,
            'destroyed' => true,
            'horde' => ['regenerationScheduled' => true],
        ]);
        self::assertFalse($session->shouldRegenerate());
        self::assertFalse($session->isDestroyed());
    }

    #[Test]
    public function testClearLifecycleFlagsClearsBoth(): void
    {
        $session = $this->createSession();
        $session->scheduleRegeneration();
        $session->markDestroyed();
        $session->clearLifecycleFlags();
        self::assertFalse($session->shouldRegenerate());
        self::assertFalse($session->isDestroyed());
    }

    #[Test]
    public function testClearLifecycleFlagsIsIdempotent(): void
    {
        // Clearing already-clear flags is a valid no-op for executors
        // that always clear regardless of who set what.
        $session = $this->createSession();
        $session->clearLifecycleFlags();
        $session->clearLifecycleFlags();
        self::assertFalse($session->shouldRegenerate());
        self::assertFalse($session->isDestroyed());
    }

    // ---------------------------------------------------------------
    // clearScope() / clearScopeWithPrefixes()
    // ---------------------------------------------------------------

    #[Test]
    public function testClearScopeWipesEntireApp(): void
    {
        $session = $this->createSession();
        $session->setScoped('imp', 'auth/userId', 'alice');
        $session->setScoped('imp', 'mailbox', 'INBOX');
        $session->setScoped('horde', 'auth/userId', 'alice');

        $session->clearScope('imp');

        self::assertSame([], $session->keysForApp('imp'));
        // horde scope must remain untouched.
        self::assertSame(['auth/userId'], $session->keysForApp('horde'));
        self::assertTrue($session->isDirty());
    }

    #[Test]
    public function testClearScopeIsNoOpWhenScopeAbsent(): void
    {
        $session = $this->createSession();
        $session->clearScope('never-touched');
        self::assertFalse($session->isDirty());
    }

    #[Test]
    public function testClearScopeWithPrefixesRemovesMatching(): void
    {
        $session = $this->createSession();
        $session->setScoped('horde', 'auth/userId', 'alice');
        $session->setScoped('horde', 'auth_app/imp', 'imp-creds');
        $session->setScoped('horde', 'nls/curr_default', 'en_US');
        $session->setScoped('horde', 'theme', 'default');

        $session->clearScopeWithPrefixes('horde', ['auth/', 'auth_app/']);

        $remaining = $session->keysForApp('horde');
        sort($remaining);
        self::assertSame(['nls/curr_default', 'theme'], $remaining);
    }

    #[Test]
    public function testClearScopeWithPrefixesEmptyArrayIsNoOp(): void
    {
        $session = $this->createSession();
        $session->setScoped('horde', 'auth/userId', 'alice');

        $session->clearScopeWithPrefixes('horde', []);

        self::assertSame(['auth/userId'], $session->keysForApp('horde'));
    }

    #[Test]
    public function testClearScopeWithPrefixesIsNoOpWhenScopeAbsent(): void
    {
        $session = $this->createSession();
        $session->clearScopeWithPrefixes('never-touched', ['auth/']);
        self::assertFalse($session->isDirty());
    }
}
