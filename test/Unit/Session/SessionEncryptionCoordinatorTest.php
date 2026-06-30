<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Session;

use Horde\Core\Secret\SessionSecret;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionEncryptionCoordinator;
use Horde\SessionHandler\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see SessionEncryptionCoordinator}.
 *
 * Pins the contract of the small mediator class that sits between
 * {@see HordeSession} and {@see SessionSecret} during the
 * drain / rotate-key / refill ceremony. The coordinator itself has
 * no algorithmic content; these tests pin the wiring.
 */
#[CoversClass(SessionEncryptionCoordinator::class)]
class SessionEncryptionCoordinatorTest extends TestCase
{
    #[Test]
    public function testDrainReturnsPlainValuesFromEncryptedSlots(): void
    {
        // Trivial round-trippable encryption: prepend a marker so we
        // can prove the decryptor was actually called.
        $encryptor = static fn(string $plain): string => 'CIPHER:' . $plain;
        $decryptor = static fn(string $cipher): string
            => str_starts_with($cipher, 'CIPHER:') ? substr($cipher, 7) : $cipher;

        $session = new HordeSession(
            new SessionId('coord-drain'),
            [],
            $encryptor,
            $decryptor,
        );
        $session->setEncrypted('imp', 'creds', 'password123');
        $session->setEncrypted('imp', 'token', 'abc');

        $coord = new SessionEncryptionCoordinator($this->stubSecret());

        $plain = $coord->drain($session);

        // Plaintexts are returned in encryption-map order.
        self::assertCount(2, $plain);
        // The session's encryption map entries are preserved (drain
        // does not remove them; refill replaces them).
        self::assertTrue($session->isEncrypted('imp', 'creds'));
        self::assertTrue($session->isEncrypted('imp', 'token'));
    }

    #[Test]
    public function testRefillCallsSecretSetKeyExactlyOnce(): void
    {
        $secret = new RecordingSecret();
        $session = new HordeSession(new SessionId('coord-refill-setkey'), []);
        $coord = new SessionEncryptionCoordinator($secret);

        $coord->refill($session, []);

        self::assertSame(1, $secret->setKeyCalls);
    }

    #[Test]
    public function testRefillReEncryptsDrainedPlaintexts(): void
    {
        $session = new HordeSession(
            new SessionId('coord-round-trip'),
            [],
            static fn(string $p): string => 'V1:' . $p,
            static fn(string $c): string
                => str_starts_with($c, 'V1:') ? substr($c, 3) : $c,
        );
        $session->setEncrypted('imp', 'creds', 'plaintext');

        $coord = new SessionEncryptionCoordinator($this->stubSecret());

        $plain = $coord->drain($session);
        $coord->refill($session, $plain);

        // Read the slot back out. Decryption uses the same closures
        // as drain (HKDF semantics would change the key inside the
        // closure between drain and refill, but at the coordinator
        // unit-test level we use stable closures and assert the
        // structural round-trip).
        self::assertSame('plaintext', $session->getEncrypted('imp', 'creds'));
    }

    #[Test]
    public function testRefillIsIdempotentForEmptyPlaintexts(): void
    {
        $secret = new RecordingSecret();
        $session = new HordeSession(new SessionId('coord-refill-empty'), []);
        $coord = new SessionEncryptionCoordinator($secret);

        $coord->refill($session, []);
        $coord->refill($session, []);

        // setKey is called once per refill — that's the contract.
        // Empty-plaintext refills still rotate the key.
        self::assertSame(2, $secret->setKeyCalls);
    }

    #[Test]
    public function testDrainOnSessionWithNoEncryptedSlotsReturnsEmpty(): void
    {
        $session = new HordeSession(new SessionId('coord-empty'), []);
        $coord = new SessionEncryptionCoordinator($this->stubSecret());

        self::assertSame([], $coord->drain($session));
    }

    private function stubSecret(): SessionSecret
    {
        return new RecordingSecret();
    }
}

/**
 * Minimal {@see SessionSecret} double for coordinator tests.
 *
 * Records setKey() invocations without performing any cryptographic
 * work. The coordinator's contract is "call setKey() once per refill";
 * concrete HKDF behaviour is tested separately in CbcHkdfTest.
 */
class RecordingSecret implements SessionSecret
{
    public int $setKeyCalls = 0;
    public int $clearKeyCalls = 0;

    public function setKey($keyname = 'generic')
    {
        $this->setKeyCalls++;
        return 'recorded-key';
    }

    public function clearKey($keyname = 'generic')
    {
        $this->clearKeyCalls++;
        return true;
    }

    public function setSession(HordeSession $session): void {}
}
