<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Session;

use Horde\Core\Session\EncryptedValuesInterface;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionMetaInterface;
use Horde\SessionHandler\Exception\SessionException;
use Horde\SessionHandler\Session;
use Horde\SessionHandler\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

        self::assertSame('alice', $payload['horde']['auth/userId']);
        self::assertSame('INBOX', $payload['imp']['mailbox']);
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

        // Simulate a legacy session payload with encryption map
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
                'auth_app/imp' => 'ENC:' . serialize('imp-password'),
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
}
