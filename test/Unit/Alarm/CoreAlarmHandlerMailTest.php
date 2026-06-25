<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Alarm;

use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use Horde_Alarm;
use Horde_Alarm_Exception;
use Horde_Alarm_Null;
use Horde_Core_Alarm_Handler_Mail;
use Horde_Mail_Transport_Mock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Horde_Core_Alarm_Handler_Mail defers Horde_Mail resolution
 * to first notify(). The portal render path that triggered imp #66 ran
 * the eager-resolution flavour during Horde_Notification handling and
 * the credentials-slot decrypt that followed killed the request.
 */
#[CoversClass(Horde_Core_Alarm_Handler_Mail::class)]
class CoreAlarmHandlerMailTest extends TestCase
{
    /**
     * Build a real Injector pre-loaded with a counter so we can assert
     * how many times Horde_Mail was resolved.
     *
     * @return array{0: Injector, 1: \stdClass} Injector + counter object
     *                                          whose `count` property is
     *                                          incremented on each
     *                                          resolution.
     */
    private function injectorWithMailCounter(): array
    {
        $injector = new Injector(new TopLevel());
        $counter = new \stdClass();
        $counter->count = 0;
        $transport = new Horde_Mail_Transport_Mock();
        $injector->addBinder(
            'Horde_Mail',
            new class ($counter, $transport) implements \Horde\Injector\Binder {
                public function __construct(
                    private \stdClass $counter,
                    private Horde_Mail_Transport_Mock $transport,
                ) {}

                public function create(\Horde\Injector\Injector $injector): mixed
                {
                    $this->counter->count++;

                    return $this->transport;
                }

                public function equals(\Horde\Injector\Binder $otherBinder): bool
                {
                    return false;
                }
            },
        );

        return [$injector, $counter];
    }

    /**
     * Identity factory that returns an identity whose
     * getDefaultFromAddress() yields a fixed email.
     */
    private function identityFactory(string $email = 'user@example.org'): object
    {
        return new class ($email) {
            public function __construct(private string $email) {}

            public function create(string $user): object
            {
                return new class ($this->email) {
                    public function __construct(private string $email) {}

                    public function getDefaultFromAddress(bool $bare): string
                    {
                        return $this->email;
                    }
                };
            }
        };
    }

    /**
     * Attach a null Horde_Alarm to a handler so notify() can call
     * `$this->alarm->internal(...)` without exploding.
     */
    private function attachAlarm(Horde_Core_Alarm_Handler_Mail $handler): void
    {
        $handler->alarm = new Horde_Alarm_Null();
    }

    #[Test]
    public function testConstructorDoesNotResolveMail(): void
    {
        [$injector, $counter] = $this->injectorWithMailCounter();

        new Horde_Core_Alarm_Handler_Mail([
            'injector' => $injector,
            'identity' => $this->identityFactory(),
        ]);

        self::assertSame(0, $counter->count);
    }

    #[Test]
    public function testNotifyResolvesMailOnFirstCallOnly(): void
    {
        [$injector, $counter] = $this->injectorWithMailCounter();

        $handler = new Horde_Core_Alarm_Handler_Mail([
            'injector' => $injector,
            'identity' => $this->identityFactory(),
        ]);
        $this->attachAlarm($handler);

        $alarm = [
            'id' => 'a1',
            'user' => 'u',
            'title' => 'T',
            'text' => 'body',
            'params' => ['mail' => ['email' => 'to@example.org']],
            'internal' => [],
        ];
        $handler->notify($alarm);
        self::assertSame(1, $counter->count);

        // Reset the sent-marker so the handler doesn't short-circuit.
        $alarm['internal'] = [];
        $handler->notify($alarm);
        self::assertSame(1, $counter->count, 'transport cached on second notify');
    }

    #[Test]
    public function testNotifySkipsWhenAlarmAlreadySent(): void
    {
        [$injector, $counter] = $this->injectorWithMailCounter();

        $handler = new Horde_Core_Alarm_Handler_Mail([
            'injector' => $injector,
            'identity' => $this->identityFactory(),
        ]);
        $this->attachAlarm($handler);

        $handler->notify([
            'id' => 'a1',
            'user' => 'u',
            'title' => 'T',
            'text' => 'body',
            'params' => ['mail' => ['email' => 'to@example.org']],
            'internal' => ['mail' => ['sent' => true]],
        ]);

        self::assertSame(0, $counter->count, 'no mail resolution for already-sent alarm');
    }

    #[Test]
    public function testNotifyWithoutEmailOrUserIsNoOp(): void
    {
        [$injector, $counter] = $this->injectorWithMailCounter();

        $handler = new Horde_Core_Alarm_Handler_Mail([
            'injector' => $injector,
            'identity' => $this->identityFactory(),
        ]);
        $this->attachAlarm($handler);

        $handler->notify([
            'id' => 'a1',
            'user' => '',
            'title' => 'T',
            'text' => 'body',
            'params' => [],
            'internal' => [],
        ]);

        self::assertSame(0, $counter->count);
    }

    #[Test]
    public function testConstructorRejectsMissingInjector(): void
    {
        $this->expectException(Horde_Alarm_Exception::class);
        new Horde_Core_Alarm_Handler_Mail([
            'identity' => $this->identityFactory(),
        ]);
    }

    #[Test]
    public function testConstructorRejectsMissingIdentity(): void
    {
        [$injector] = $this->injectorWithMailCounter();
        $this->expectException(Horde_Alarm_Exception::class);
        new Horde_Core_Alarm_Handler_Mail([
            'injector' => $injector,
        ]);
    }

    #[Test]
    public function testConstructorRejectsNonInjector(): void
    {
        $this->expectException(Horde_Alarm_Exception::class);
        new Horde_Core_Alarm_Handler_Mail([
            'injector' => 'not-an-injector',
            'identity' => $this->identityFactory(),
        ]);
    }

    #[Test]
    public function testConstructorRejectsIdentityWithoutCreate(): void
    {
        [$injector] = $this->injectorWithMailCounter();
        $this->expectException(Horde_Alarm_Exception::class);
        new Horde_Core_Alarm_Handler_Mail([
            'injector' => $injector,
            'identity' => new \stdClass(),
        ]);
    }
}
