<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license http://www.horde.org/licenses/lgpl21 LGPL-2.1
 */

namespace Horde\Core\Factory;

use Closure;
use Horde\Auth\AccessPolicy;
use Horde\Auth\CredentialProvider;
use Horde\Auth\Imap;
use Horde\Auth\Ldap;
use Horde\Auth\Policy\CompoundPolicy;
use Horde\Auth\Policy\LockoutPolicy;
use Horde\Auth\Policy\NullPolicy;
use Horde\Auth\Sql;
use Horde\Core\Auth\AuthService;
use Horde\Core\Auth\CredentialProviderRegistry;
use Horde\Core\Auth\IdentityBridgeService;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Service\HordeDbService;
use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\SecureMode;
use Horde_Injector;
use Horde_Ldap;
use RuntimeException;

/**
 * Factory for creating AuthService with configured backend, policy, and
 * identity bridge from application configuration.
 */
class AuthServiceFactory
{
    public function create(Horde_Injector $injector): AuthService
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        $driver = $state->get('auth.driver', 'sql');
        $params = $state->get('auth.params', []);

        $provider = $this->buildProvider($driver, $params, $injector);
        $policy = $this->buildPolicy($params, $injector);
        $identityBridge = $injector->getInstance(IdentityBridgeService::class);

        return new AuthService($provider, $policy, $identityBridge);
    }

    private function buildProvider(string $driver, array $params, Horde_Injector $injector): CredentialProvider
    {
        return match ($driver) {
            'sql', 'auto' => $this->createSqlProvider($params, $injector),
            'ldap' => $this->createLdapProvider($params, $injector),
            'imap' => $this->createImapProvider($params, $injector),
            'composite' => $this->createCompositeProvider($params, $injector),
            default => throw new RuntimeException("Unsupported auth driver: $driver"),
        };
    }

    private function buildPolicy(array $params, Horde_Injector $injector): AccessPolicy
    {
        $loginBlock = $params['login_block'] ?? false;

        if (!$loginBlock) {
            return new NullPolicy();
        }

        $storageFactory = new AuthStorageFactory();
        $tracker = $storageFactory->createAttemptTracker($injector);
        $lockManager = $storageFactory->createLockManager($injector);

        $maxAttempts = (int) ($params['max_login_attempts'] ?? 5);
        $lockDuration = (int) ($params['lock_duration'] ?? 900);

        $lockout = new LockoutPolicy($tracker, $lockManager, $maxAttempts, $lockDuration);

        return new CompoundPolicy($lockout);
    }

    private function createSqlProvider(array $params, Horde_Injector $injector): Sql
    {
        $dbService = $injector->getInstance(HordeDbService::class);

        return new Sql(
            db: $dbService->getAdapter(),
            table: $params['table'] ?? 'horde_users',
            usernameField: $params['username_field'] ?? 'user_uid',
            passwordField: $params['password_field'] ?? 'user_pass',
            encryption: $params['encryption'] ?? 'crypt-blowfish',
            showEncryption: $params['show_encryption'] ?? false,
        );
    }

    private function createLdapProvider(array $params, Horde_Injector $injector): Ldap
    {
        $ldap = $injector->getInstance(Horde_Ldap::class);

        return new Ldap(
            ldap: $ldap,
            baseDn: $params['basedn'] ?? '',
            uidAttribute: $params['uid'] ?? 'uid',
            objectClass: (array) ($params['objectclass'] ?? ['posixAccount']),
            activeDirectory: $params['ad'] ?? false,
            filter: $params['filter'] ?? null,
        );
    }

    private function createImapProvider(array $params, Horde_Injector $injector): Imap
    {
        $hostspec = $params['hostspec'] ?? 'localhost';
        $port = isset($params['port']) ? (int) $params['port'] : null;
        $secure = match ($params['secure'] ?? 'none') {
            'ssl', 'tls' => SecureMode::Tls,
            'starttls' => SecureMode::StartTls,
            default => SecureMode::None,
        };

        $clientFactory = $injector->getInstance('Horde_Imap_Client_Factory');

        return new Imap(
            clientFactory: $clientFactory,
            hostspec: $hostspec,
            secure: $secure,
            port: $port,
        );
    }

    private function createCompositeProvider(array $params, Horde_Injector $injector): CredentialProviderRegistry
    {
        $providers = [];

        foreach (($params['drivers'] ?? []) as $subDriver => $subParams) {
            $providers[] = $this->buildProvider($subDriver, $subParams, $injector);
        }

        if (empty($providers)) {
            throw new RuntimeException('Composite auth driver requires at least one sub-driver in params.drivers');
        }

        return new CredentialProviderRegistry(...$providers);
    }
}
