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
use Horde\Core\Session\HordeSessionFactory;
use Horde\Core\Session\SessionMetaInterface;
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use Horde\SessionHandler\DefaultSessionFactory;
use Horde\SessionHandler\SessionId;
use Horde_Core_Secret_Cbc;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HordeSessionFactory::class)]
class HordeSessionFactoryTest extends TestCase
{
    #[Test]
    public function testExtendsDefaultSessionFactory(): void
    {
        $factory = new HordeSessionFactory();
        self::assertInstanceOf(DefaultSessionFactory::class, $factory);
    }

    #[Test]
    public function testCreateNewReturnsHordeSession(): void
    {
        $factory = new HordeSessionFactory();
        $session = $factory->createNew(new SessionId('new-test'));

        self::assertInstanceOf(HordeSession::class, $session);
    }

    #[Test]
    public function testCreateNewSetsBeginTimestamp(): void
    {
        $factory = new HordeSessionFactory();
        $before = time();
        $session = $factory->createNew(new SessionId('begin-test'));
        $after = time();

        $begin = $session->getSessionBegin();
        self::assertNotNull($begin);
        self::assertGreaterThanOrEqual($before, $begin->getTimestamp());
        self::assertLessThanOrEqual($after, $begin->getTimestamp());
    }

    #[Test]
    public function testRestoreReturnsHordeSession(): void
    {
        $factory = new HordeSessionFactory();
        $session = $factory->restore(new SessionId('restore-test'), [
            '_b' => 1700000000,
            'horde' => ['auth/userId' => 'alice'],
        ]);

        self::assertInstanceOf(HordeSession::class, $session);
        self::assertInstanceOf(SessionMetaInterface::class, $session);
        self::assertSame('alice', $session->getAuthenticatedUser());
    }

    #[Test]
    public function testRestorePassesTwoLevelPayloadDirectly(): void
    {
        $factory = new HordeSessionFactory();
        $payload = [
            '_b' => 1700000000,
            'horde' => [
                'auth/userId' => 'alice',
                'auth_app/imp' => 'cred',
            ],
            'imp' => ['mailbox' => 'INBOX'],
        ];

        $session = $factory->restore(new SessionId('payload-test'), $payload);

        // Data is accessible via scoped methods
        self::assertSame('alice', $session->getScoped('horde', 'auth/userId'));
        self::assertSame('INBOX', $session->getScoped('imp', 'mailbox'));

        // Payload round-trips unchanged
        self::assertSame($payload, $session->toPayload());
    }

    #[Test]
    public function testCreateNewWithEncryptionClosures(): void
    {
        $encryptor = fn(string $p): string => 'ENC:' . $p;
        $decryptor = fn(string $c): string => substr($c, 4);

        $factory = new HordeSessionFactory($encryptor, $decryptor);
        $session = $factory->createNew(new SessionId('enc-test'));

        // Encryption works
        $session->setEncrypted('horde', 'auth_app/imp', 'secret');
        self::assertSame('secret', $session->getEncrypted('horde', 'auth_app/imp'));
    }

    #[Test]
    public function testRestoreWithEncryptionClosures(): void
    {
        $decryptor = fn(string $c): string => substr($c, 4);

        // Encrypted plaintext is Horde_Pack-packed (matching the legacy
        // Horde_Session::set(..., ENCRYPT) wire format). Reproduce that here
        // rather than using bare serialize().
        $pack = new \Horde_Pack();
        $packed = $pack->pack('password', ['compress' => 0]);

        $factory = new HordeSessionFactory(null, $decryptor);
        $session = $factory->restore(new SessionId('dec-test'), [
            '_e' => ['horde' => ['auth_app/imp' => true]],
            'horde' => ['auth_app/imp' => 'ENC:' . $packed],
        ]);

        self::assertTrue($session->isEncrypted('horde', 'auth_app/imp'));
        self::assertSame('password', $session->getEncrypted('horde', 'auth_app/imp'));
    }

    #[Test]
    public function testRestoreEmptyPayload(): void
    {
        $factory = new HordeSessionFactory();
        $session = $factory->restore(new SessionId('empty-test'), []);

        self::assertInstanceOf(HordeSession::class, $session);
        self::assertNull($session->getAuthenticatedUser());
    }

    /**
     * Regression: the DI auto-wire path used to construct a HordeSession
     * with null encryptor/decryptor, which made setEncrypted() throw on
     * every login (Horde_Registry::setAuthCredential delegates here through
     * AuthCredentialStore). The factory must resolve Horde_Core_Secret_Cbc
     * from the injector and wrap it in lazy closures so the per-session
     * key is read at call time, not at factory time.
     */
    #[Test]
    public function testCreateWiresEncryptionClosuresFromInjector(): void
    {
        $secret = $this->createMock(Horde_Core_Secret_Cbc::class);
        $secret->expects(self::exactly(2))
            ->method('getKey')
            ->willReturn('the-session-key');
        $secret->expects(self::once())
            ->method('write')
            ->willReturnCallback(static function (string $k, string $p): string {
                self::assertSame('the-session-key', $k);
                return 'CT:' . $p;
            });
        $secret->expects(self::once())
            ->method('read')
            ->willReturnCallback(static function (string $k, string $c): string {
                self::assertSame('the-session-key', $k);
                return substr($c, 3);
            });

        $injector = new Injector(new TopLevel());
        $injector->setInstance('Horde_Secret_Cbc', $secret);

        $factory = new HordeSessionFactory();
        $session = $factory->create($injector);

        $session->setEncrypted('horde', 'auth_app/imp', ['user' => 'alice']);
        self::assertSame(['user' => 'alice'], $session->getEncrypted('horde', 'auth_app/imp'));
    }

    /**
     * The closures must defer key resolution until call time. Horde_Session::clean()
     * calls Horde_Core_Secret_Cbc::setKey() during login, after the factory has
     * already built the session. Capturing the key at factory time would lock
     * in the pre-login key.
     */
    #[Test]
    public function testCreateClosuresResolveKeyLazily(): void
    {
        $keys = ['first-key', 'second-key'];
        $callIndex = 0;

        $secret = $this->createMock(Horde_Core_Secret_Cbc::class);
        $secret->expects(self::exactly(2))
            ->method('getKey')
            ->willReturnCallback(
                static function () use (&$callIndex, $keys): string {
                    return $keys[$callIndex++] ?? 'overflow';
                }
            );
        $observedKeys = [];
        $secret->expects(self::exactly(2))
            ->method('write')
            ->willReturnCallback(
                static function (string $key, string $plaintext) use (&$observedKeys): string {
                    $observedKeys[] = $key;
                    return 'CT:' . $plaintext;
                }
            );

        $injector = new Injector(new TopLevel());
        $injector->setInstance('Horde_Secret_Cbc', $secret);

        $factory = new HordeSessionFactory();
        $session = $factory->create($injector);

        // Two writes against the same session: the key changes between calls,
        // proving the closure asks the secret for getKey() each time.
        $session->setEncrypted('horde', 'auth_app/imp', 'one');
        $session->setEncrypted('horde', 'auth_app/turba', 'two');

        self::assertSame(['first-key', 'second-key'], $observedKeys);
    }
}
