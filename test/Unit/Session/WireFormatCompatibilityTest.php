<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Session;

use Horde\Core\Session\HordeSession;
use Horde\SessionHandler\SessionId;
use Horde_Pack;
use Horde_Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Pin the wire-format equivalence between the legacy Horde_Session shim and
 * the modern HordeSession.
 *
 * After the Horde_Pack-native HordeSession refactor, the modern session owns
 * the wire format previously produced by the legacy Horde_Session::set path.
 * Both writers must produce identical bytes at $_SESSION[$app][$name] for the
 * same input value, and both readers must recover the same PHP value from
 * those bytes.
 *
 * The tests use the legacy shim's no-arg constructor path (which resolves
 * dependencies via $GLOBALS['injector']) plus a hand-built HordeSession for
 * the direct comparison.
 */
#[CoversClass(HordeSession::class)]
#[CoversClass(Horde_Session::class)]
class WireFormatCompatibilityTest extends TestCase
{
    /**
     * Read the raw wire-format bytes at the slot using the modern session's
     * payload accessor, bypassing the readers' decode logic. This is what
     * actually lands in $_SESSION on shutdown.
     */
    private function rawAt(HordeSession $session, string $app, string $name): mixed
    {
        return $session->toPayload()[$app][$name] ?? null;
    }

    // ---------------------------------------------------------------
    // Equivalence: shim::set(...) and modern::setScoped/setEncrypted
    // produce identical bytes at the slot.
    // ---------------------------------------------------------------

    #[Test]
    public function legacyAndModernProduceIdenticalBytesForString(): void
    {
        $modernA = new HordeSession(new SessionId('a'));
        $modernB = new HordeSession(new SessionId('b'));
        $shim = new Horde_Session($modernB);

        $modernA->setScoped('imp', 'mailbox', 'INBOX');
        $shim->set('imp', 'mailbox', 'INBOX');

        $this->assertSame(
            $this->rawAt($modernA, 'imp', 'mailbox'),
            $this->rawAt($modernB, 'imp', 'mailbox'),
            'Modern and legacy writers should produce identical bytes for a string',
        );
        $this->assertSame("\0INBOX", $this->rawAt($modernA, 'imp', 'mailbox'));
    }

    #[Test]
    public function legacyAndModernProduceIdenticalBytesForArray(): void
    {
        $modernA = new HordeSession(new SessionId('a'));
        $modernB = new HordeSession(new SessionId('b'));
        $shim = new Horde_Session($modernB);

        $array = ['user' => 'alice', 'role' => 'admin', 'level' => 7];

        $modernA->setScoped('horde', 'profile', $array);
        $shim->set('horde', 'profile', $array);

        $this->assertSame(
            $this->rawAt($modernA, 'horde', 'profile'),
            $this->rawAt($modernB, 'horde', 'profile'),
            'Modern and legacy writers should produce identical bytes for an array',
        );

        // Sanity: round-trip both through Horde_Pack — same input should
        // produce a deterministic packed shape.
        $pack = new Horde_Pack();
        $expected = $pack->pack($array, ['compress' => 0]);
        $this->assertSame($expected, $this->rawAt($modernA, 'horde', 'profile'));
    }

    #[Test]
    public function legacyAndModernProduceIdenticalBytesForObject(): void
    {
        $modernA = new HordeSession(new SessionId('a'));
        $modernB = new HordeSession(new SessionId('b'));
        $shim = new Horde_Session($modernB);

        $obj = new stdClass();
        $obj->user = 'alice';
        $obj->role = 'admin';

        $modernA->setScoped('horde', 'who', $obj);
        $shim->set('horde', 'who', $obj);

        $this->assertSame(
            $this->rawAt($modernA, 'horde', 'who'),
            $this->rawAt($modernB, 'horde', 'who'),
            'Modern and legacy writers should produce identical bytes for an object',
        );

        $pack = new Horde_Pack();
        $expected = $pack->pack($obj, ['compress' => 0, 'phpob' => true]);
        $this->assertSame($expected, $this->rawAt($modernA, 'horde', 'who'));
    }

    #[Test]
    public function legacyAndModernStoreIntegerRaw(): void
    {
        $modernA = new HordeSession(new SessionId('a'));
        $modernB = new HordeSession(new SessionId('b'));
        $shim = new Horde_Session($modernB);

        $modernA->setScoped('horde', 'count', 42);
        $shim->set('horde', 'count', 42);

        $this->assertSame(42, $this->rawAt($modernA, 'horde', 'count'));
        $this->assertSame(42, $this->rawAt($modernB, 'horde', 'count'));
    }

    #[Test]
    public function legacyAndModernProduceIdenticalEncryptedBytesForArray(): void
    {
        // Identity encryptor/decryptor — pin the plaintext shape, not the
        // encryption algorithm.
        $encryptor = static fn(string $p): string => 'ENC:' . $p;
        $decryptor = static fn(string $c): string => substr($c, 4);

        $modernA = new HordeSession(new SessionId('a'), [], $encryptor, $decryptor);
        $modernB = new HordeSession(new SessionId('b'), [], $encryptor, $decryptor);
        $shim = new Horde_Session($modernB);

        $credentials = ['password' => 's3cret', 'mode' => 'imap'];

        $modernA->setEncrypted('horde', 'auth_app/imp', $credentials);
        $shim->set('horde', 'auth_app/imp', $credentials, Horde_Session::ENCRYPT);

        $this->assertSame(
            $this->rawAt($modernA, 'horde', 'auth_app/imp'),
            $this->rawAt($modernB, 'horde', 'auth_app/imp'),
            'Encrypted plaintext shape must match between legacy and modern writers',
        );

        // The plaintext side is Horde_Pack-packed.
        $pack = new Horde_Pack();
        $expectedPacked = $pack->pack($credentials, ['compress' => 0]);
        $this->assertSame(
            'ENC:' . $expectedPacked,
            $this->rawAt($modernA, 'horde', 'auth_app/imp'),
        );

        // Both readers recover the original.
        $this->assertSame($credentials, $modernA->getEncrypted('horde', 'auth_app/imp'));
        $this->assertSame($credentials, $modernB->getEncrypted('horde', 'auth_app/imp'));
    }

    // ---------------------------------------------------------------
    // Cross-reader: bytes written via either writer round-trip through
    // either reader.
    // ---------------------------------------------------------------

    public static function roundTripValues(): array
    {
        return [
            'string' => ['hello'],
            'empty string' => [''],
            'string with null bytes' => ["a\0b\0c"],
            'integer' => [42],
            'zero' => [0],
            'negative integer' => [-7],
            'float' => [3.14],
            'boolean true' => [true],
            'boolean false' => [false],
            'array of strings' => [['a', 'b', 'c']],
            'associative array' => [['user' => 'alice', 'role' => 'admin']],
            'nested array' => [['outer' => ['inner' => 'value']]],
        ];
    }

    #[Test]
    #[DataProvider('roundTripValues')]
    public function modernWriteShimRead(mixed $value): void
    {
        $modern = new HordeSession(new SessionId('round-trip'));
        $shim = new Horde_Session($modern);

        $modern->setScoped('app', 'key', $value);

        $this->assertSame($value, $shim->get('app', 'key'));
    }

    #[Test]
    #[DataProvider('roundTripValues')]
    public function shimWriteModernRead(mixed $value): void
    {
        $modern = new HordeSession(new SessionId('round-trip'));
        $shim = new Horde_Session($modern);

        $shim->set('app', 'key', $value);

        $this->assertSame($value, $modern->getScoped('app', 'key'));
    }

    // ---------------------------------------------------------------
    // Recorded-format readability: an on-disk payload taken from a
    // pre-shim Horde_Session writer is readable by the modern reader.
    // ---------------------------------------------------------------

    #[Test]
    public function preShimRecordedPayloadStillReadable(): void
    {
        // Reproduce what last week's pre-shim Horde_Session::set wrote:
        // - strings: NOT_SERIALIZED-prefixed
        // - arrays/objects: Horde_Pack::pack
        // - other scalars: raw
        $pack = new Horde_Pack();
        $recorded = [
            '_b' => 1700000000,
            '_e' => [
                'horde' => ['auth_app/imp' => true],
            ],
            'horde' => [
                'auth/userId' => "\0alice",
                'auth/timestamp' => 1700000000,
                'profile' => $pack->pack(['role' => 'admin'], ['compress' => 0]),
                'auth_app/imp' => 'ENC:' . $pack->pack(
                    ['password' => 'secret'],
                    ['compress' => 0],
                ),
            ],
            'imp' => [
                'mailbox' => "\0INBOX",
            ],
        ];

        $decryptor = static fn(string $c): string => substr($c, 4);
        $session = new HordeSession(
            new SessionId('recorded'),
            $recorded,
            null,
            $decryptor,
        );

        $this->assertSame('alice', $session->getScoped('horde', 'auth/userId'));
        $this->assertSame(1700000000, $session->getScoped('horde', 'auth/timestamp'));
        $this->assertSame(['role' => 'admin'], $session->getScoped('horde', 'profile'));
        $this->assertSame('INBOX', $session->getScoped('imp', 'mailbox'));
        $this->assertSame(
            ['password' => 'secret'],
            $session->getEncrypted('horde', 'auth_app/imp'),
        );
    }
}
