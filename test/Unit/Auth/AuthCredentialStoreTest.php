<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Core\Test\Unit\Auth;

use Horde\Core\Auth\AuthCredentialStore;
use Horde\Core\Session\HordeSession;
use Horde\SessionHandler\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pin AuthCredentialStore's behaviour against the legacy
 * Horde_Registry::setAuthCredential / getAuthCredential / _getAuthCredentials
 * trio it replaces.
 */
#[CoversClass(AuthCredentialStore::class)]
class AuthCredentialStoreTest extends TestCase
{
    /**
     * Build a HordeSession with an identity-shaped encryptor/decryptor pair
     * plus the `auth/credentials` base-app pointer pre-populated.
     */
    private function sessionWithBaseApp(string $baseApp): HordeSession
    {
        $encryptor = static fn(string $p): string => 'ENC:' . $p;
        $decryptor = static fn(string $c): string => substr($c, 4);

        $session = new HordeSession(
            new SessionId('store-test'),
            [],
            $encryptor,
            $decryptor,
        );
        $session->setScoped('horde', 'auth/credentials', $baseApp);

        return $session;
    }

    #[Test]
    public function getReturnsFalseWhenNoBaseAppSet(): void
    {
        $encryptor = static fn(string $p): string => $p;
        $decryptor = static fn(string $c): string => $c;
        $session = new HordeSession(
            new SessionId('no-base'),
            [],
            $encryptor,
            $decryptor,
        );
        $store = new AuthCredentialStore($session);

        self::assertFalse($store->get(null));
        self::assertFalse($store->get('imp'));
    }

    #[Test]
    public function setAndGetForBaseApp(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = new AuthCredentialStore($session);

        $store->set(null, ['password' => 's3cret']);

        self::assertSame(['password' => 's3cret'], $store->get(null));
        self::assertSame(['password' => 's3cret'], $store->get('horde'));
    }

    #[Test]
    public function setMarksAppInitialized(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = new AuthCredentialStore($session);

        self::assertFalse($store->isInitialized('horde'));

        $store->set(null, ['password' => 's3cret']);

        self::assertTrue($store->isInitialized('horde'));
    }

    #[Test]
    public function setOneAppendsToExistingCredentials(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = new AuthCredentialStore($session);

        $store->set(null, ['password' => 's3cret']);

        self::assertTrue($store->setOne(null, 'mode', 'imap'));

        self::assertSame(
            ['password' => 's3cret', 'mode' => 'imap'],
            $store->get(null),
        );
    }

    #[Test]
    public function setOneReturnsFalseWhenNoBaseApp(): void
    {
        $encryptor = static fn(string $p): string => $p;
        $decryptor = static fn(string $c): string => $c;
        $session = new HordeSession(
            new SessionId('no-base'),
            [],
            $encryptor,
            $decryptor,
        );
        $store = new AuthCredentialStore($session);

        self::assertFalse($store->setOne(null, 'password', 's3cret'));
    }

    #[Test]
    public function dedupAgainstBaseAppStoresTrueForMatchingEntry(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = new AuthCredentialStore($session);

        $credentials = ['password' => 's3cret'];
        $store->set(null, $credentials);

        // imp app gets the same credentials as the base — should be stored
        // as `true` per the dedup rule.
        $store->set('imp', $credentials);

        // Reading imp materialises the credentials by following the dedup
        // pointer back to the base app.
        self::assertSame($credentials, $store->get('imp'));

        // The raw payload at imp's slot is `true` (dedup marker), not the
        // credentials array.
        $raw = $session->toPayload()['horde']['auth_app/imp'] ?? null;
        self::assertTrue($raw);
    }

    #[Test]
    public function dedupAgainstBaseAppStoresEntryForDifferentValues(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = new AuthCredentialStore($session);

        $store->set(null, ['password' => 'horde-pw']);

        // imp gets different credentials — should be persisted as its own
        // encrypted entry, not deduped.
        $store->set('imp', ['password' => 'imp-pw']);

        self::assertSame(['password' => 'horde-pw'], $store->get('horde'));
        self::assertSame(['password' => 'imp-pw'], $store->get('imp'));

        // The raw payload at imp is the encrypted entry, not `true`.
        $raw = $session->toPayload()['horde']['auth_app/imp'] ?? null;
        self::assertNotTrue($raw);
        self::assertIsString($raw);
    }

    #[Test]
    public function getFallsBackToBaseAppWhenAppSlotMissing(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = new AuthCredentialStore($session);

        $store->set(null, ['password' => 'horde-pw']);

        // Asking for a never-written app falls back to the base app's slot.
        self::assertSame(['password' => 'horde-pw'], $store->get('kronolith'));
    }

    #[Test]
    public function getReturnsFalseWhenAppAndBaseBothMissing(): void
    {
        // Base app set but the slot was never written for it.
        $session = $this->sessionWithBaseApp('horde');
        $store = new AuthCredentialStore($session);

        self::assertFalse($store->get('horde'));
        self::assertFalse($store->get('imp'));
    }

    #[Test]
    public function clearRemovesCredentialsAndInitFlag(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = new AuthCredentialStore($session);

        $store->set(null, ['password' => 's3cret']);
        self::assertTrue($store->isInitialized('horde'));

        $store->clear('horde');

        self::assertFalse($store->isInitialized('horde'));
        // get falls through to the base-app branch — no slot, so false.
        self::assertFalse($store->get('horde'));
    }

    #[Test]
    public function setUsesEncryptedStorageForCredentialsArray(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = new AuthCredentialStore($session);

        $store->set(null, ['password' => 'plain-text-secret']);

        $payload = $session->toPayload();
        $rawCredentials = $payload['horde']['auth_app/horde'] ?? null;

        // The stored bytes go through the configured encryptor closure; in
        // this fixture that's an identity prefix that proves the encryptor
        // ran. The encryption map also records the slot.
        self::assertIsString($rawCredentials);
        self::assertStringStartsWith('ENC:', $rawCredentials);
        self::assertTrue($session->isEncrypted('horde', 'auth_app/horde'));
    }

    #[Test]
    public function initFlagWiredToScopedNotEncryptedSlot(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = new AuthCredentialStore($session);

        $store->set(null, ['password' => 's3cret']);

        // The init flag is a plain boolean stored unencrypted.
        self::assertFalse($session->isEncrypted('horde', 'auth_app_init/horde'));
        self::assertTrue($session->getScoped('horde', 'auth_app_init/horde'));
    }
}
