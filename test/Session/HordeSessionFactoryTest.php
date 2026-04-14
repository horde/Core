<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Session;

use Horde\Core\Session\HordeSession;
use Horde\Core\Session\HordeSessionFactory;
use Horde\Core\Session\SessionMetaInterface;
use Horde\SessionHandler\DefaultSessionFactory;
use Horde\SessionHandler\SessionId;
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

        $factory = new HordeSessionFactory(null, $decryptor);
        $session = $factory->restore(new SessionId('dec-test'), [
            '_e' => ['horde' => ['auth_app/imp' => true]],
            'horde' => ['auth_app/imp' => 'ENC:' . serialize('password')],
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
}
