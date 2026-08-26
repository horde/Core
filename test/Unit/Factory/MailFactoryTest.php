<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package Core
 */

namespace Horde\Core\Test\Unit\Factory;

use Horde_Core_Factory_Mail;
use Horde_Core_Hooks;
use Horde_Exception_HookNotSet;
use Horde_Injector;
use Horde_Injector_TopLevel;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ensures SMTP username_auth / password_auth fall back to configured master
 * credentials when the session has an auth name but no password — the
 * horde-alarms CLI user_admin case that previously sent empty SMTP passwords.
 */
#[CoversClass(Horde_Core_Factory_Mail::class)]
class MailFactoryTest extends TestCase
{
    private array $confBackup = [];
    private $registryBackup;

    protected function setUp(): void
    {
        $this->confBackup = $GLOBALS['conf'] ?? [];
        $this->registryBackup = $GLOBALS['registry'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['conf'] = $this->confBackup;
        if ($this->registryBackup === null) {
            unset($GLOBALS['registry']);
        } else {
            $GLOBALS['registry'] = $this->registryBackup;
        }
    }

    #[Test]
    public function testCliUserAdminKeepsMasterSmtpCredentials(): void
    {
        $this->configureMailer([
            'username' => 'smtp-master@example.com',
            'password' => 'master-secret',
            'username_auth' => true,
            'password_auth' => true,
            'auth' => true,
        ]);

        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('isAuthenticated')->willReturn(true);
        $registry->method('getAuth')->willReturn('admin@example.com');
        $registry->method('getAuthCredential')
            ->with('password')
            ->willReturn(false);
        $GLOBALS['registry'] = $registry;

        [$transport, $params] = $this->factoryWithoutSmtpHook()->getConfig();

        $this->assertSame('smtp', $transport);
        $this->assertSame('smtp-master@example.com', $params['username']);
        $this->assertSame('master-secret', $params['password']);
        $this->assertArrayNotHasKey('username_auth', $params);
        $this->assertArrayNotHasKey('password_auth', $params);
    }

    #[Test]
    public function testSessionCredentialsReplaceMasterWhenPresent(): void
    {
        $this->configureMailer([
            'username' => 'smtp-master@example.com',
            'password' => 'master-secret',
            'username_auth' => true,
            'password_auth' => true,
            'auth' => true,
        ]);

        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('isAuthenticated')->willReturn(true);
        $registry->method('getAuth')->willReturn('alice@example.com');
        $registry->method('getAuthCredential')
            ->with('password')
            ->willReturn('alice-secret');
        $GLOBALS['registry'] = $registry;

        [, $params] = $this->factoryWithoutSmtpHook()->getConfig();

        $this->assertSame('alice@example.com', $params['username']);
        $this->assertSame('alice-secret', $params['password']);
    }

    #[Test]
    public function testPasswordAuthAloneKeepsMasterUsername(): void
    {
        $this->configureMailer([
            'username' => 'smtp-master@example.com',
            'password' => 'master-secret',
            'username_auth' => false,
            'password_auth' => true,
            'auth' => true,
        ]);

        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('isAuthenticated')->willReturn(true);
        $registry->method('getAuth')->willReturn('alice@example.com');
        $registry->method('getAuthCredential')
            ->with('password')
            ->willReturn('alice-secret');
        $GLOBALS['registry'] = $registry;

        [, $params] = $this->factoryWithoutSmtpHook()->getConfig();

        $this->assertSame('smtp-master@example.com', $params['username']);
        $this->assertSame('alice-secret', $params['password']);
    }

    #[Test]
    public function testEmptySessionPasswordDoesNotClearMasterPassword(): void
    {
        $this->configureMailer([
            'username' => 'smtp-master@example.com',
            'password' => 'master-secret',
            'username_auth' => true,
            'password_auth' => true,
            'auth' => true,
        ]);

        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('isAuthenticated')->willReturn(true);
        $registry->method('getAuth')->willReturn('admin@example.com');
        $registry->method('getAuthCredential')
            ->with('password')
            ->willReturn('');
        $GLOBALS['registry'] = $registry;

        [, $params] = $this->factoryWithoutSmtpHook()->getConfig();

        $this->assertSame('smtp-master@example.com', $params['username']);
        $this->assertSame('master-secret', $params['password']);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function configureMailer(array $params): void
    {
        $GLOBALS['conf'] = [
            'mailer' => [
                'type' => 'smtp',
                'params' => $params,
            ],
        ];
    }

    private function factoryWithoutSmtpHook(): Horde_Core_Factory_Mail
    {
        $hooks = $this->createMock(Horde_Core_Hooks::class);
        $hooks->method('callHook')
            ->with('smtp_credentials', 'horde', $this->anything())
            ->willThrowException(new Horde_Exception_HookNotSet());

        $injector = new Horde_Injector(new Horde_Injector_TopLevel());
        $injector->setInstance('Horde_Core_Hooks', $hooks);

        return new Horde_Core_Factory_Mail($injector);
    }
}
