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
use Horde\Core\Auth\CredentialResult;
use Horde\Core\Auth\CredentialStateMetadata;
use Horde\Core\Auth\HasCredentialsState;
use Horde\Core\Auth\InvalidationReason;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionAccessor;
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

    /**
     * Build an AuthCredentialStore against `$session` by wrapping it in
     * a fresh {@see SessionAccessor}. Production wires the accessor as
     * a shared request-scoped singleton; here every store gets its own
     * accessor holding just this session, which matches what the tests
     * actually exercise.
     */
    private function store(HordeSession $session): AuthCredentialStore
    {
        $accessor = new SessionAccessor();
        $accessor->replaceWith($session);
        return new AuthCredentialStore($accessor);
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
        $store = $this->store($session);

        self::assertFalse($store->get(null));
        self::assertFalse($store->get('imp'));
    }

    #[Test]
    public function setAndGetForBaseApp(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->set(null, ['password' => 's3cret']);

        self::assertSame(['password' => 's3cret'], $store->get(null));
        self::assertSame(['password' => 's3cret'], $store->get('horde'));
    }

    #[Test]
    public function setMarksAppInitialized(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        self::assertFalse($store->isInitialized('horde'));

        $store->set(null, ['password' => 's3cret']);

        self::assertTrue($store->isInitialized('horde'));
    }

    #[Test]
    public function setOneAppendsToExistingCredentials(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

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
        $store = $this->store($session);

        self::assertFalse($store->setOne(null, 'password', 's3cret'));
    }

    #[Test]
    public function dedupAgainstBaseAppStoresTrueForMatchingEntry(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

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
        $store = $this->store($session);

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
        $store = $this->store($session);

        $store->set(null, ['password' => 'horde-pw']);

        // Asking for a never-written app falls back to the base app's slot.
        self::assertSame(['password' => 'horde-pw'], $store->get('kronolith'));
    }

    #[Test]
    public function getReturnsFalseWhenAppAndBaseBothMissing(): void
    {
        // Base app set but the slot was never written for it.
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        self::assertFalse($store->get('horde'));
        self::assertFalse($store->get('imp'));
    }

    #[Test]
    public function clearRemovesCredentialsAndInitFlag(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

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
        $store = $this->store($session);

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
        $store = $this->store($session);

        $store->set(null, ['password' => 's3cret']);

        // The init flag is a plain boolean stored unencrypted.
        self::assertFalse($session->isEncrypted('horde', 'auth_app_init/horde'));
        self::assertTrue($session->getScoped('horde', 'auth_app_init/horde'));
    }

    #[Test]
    public function getReturnsFalseWhenSlotHoldsCorruptedScalar(): void
    {
        // Reproduces what happens when an encrypted slot decrypts to a
        // string (wrong horde_secret_key, legacy wire format, or otherwise
        // corrupted bytes). The declared array|false return type would be
        // violated if the store passed the string through.
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        // Poke a raw string into the credentials slot, bypassing set()'s
        // encryption path. Mirrors a corrupt-decryption outcome at read
        // time.
        $session->setScoped('horde', 'auth_app/horde', 'not-an-array');

        self::assertFalse($store->get('horde'));
    }

    #[Test]
    public function getReturnsFalseWhenDedupMarkerPointsAtBaseApp(): void
    {
        // Pathological case: the dedup marker `true` was written into the
        // base-app slot itself. Resolving it would mean recursing into the
        // same slot. Treat as missing rather than loop or surface `true`.
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $session->setScoped('horde', 'auth_app/horde', true);

        self::assertFalse($store->get('horde'));
    }

    // ---------------------------------------------------------------
    // Per-app credential state (HasCredentialsState + InvalidationReason)
    // ---------------------------------------------------------------

    #[Test]
    public function getStateDefaultsToNeverHadWhenNoSlot(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        // No state slot has been written; the implicit default is NeverHad.
        self::assertSame(HasCredentialsState::NeverHad, $store->getState('imp'));
    }

    #[Test]
    public function setMarksStateAsPresent(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->set('horde', ['password' => 'secret']);

        self::assertSame(HasCredentialsState::Present, $store->getState('horde'));
    }

    #[Test]
    public function setCredentialsAliasMarksStateAsPresent(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->setCredentials('horde', ['password' => 'secret']);

        self::assertSame(HasCredentialsState::Present, $store->getState('horde'));
        self::assertSame(['password' => 'secret'], $store->get('horde'));
    }

    #[Test]
    public function markInvalidatedFlipsStateAndDropsCredentials(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->set('imp', ['password' => 'secret']);
        $store->markInvalidated('imp', InvalidationReason::BackendRejected);

        self::assertSame(HasCredentialsState::Invalidated, $store->getState('imp'));
        // Credentials are no longer present in the slot.
        self::assertFalse($session->hasScoped('horde', 'auth_app/imp'));
    }

    #[Test]
    public function markInvalidatedRecordsReasonAndDetail(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->set('imp', ['password' => 'secret']);
        $store->markInvalidated('imp', InvalidationReason::PasswordChanged, 'changed via passwd/');

        $metadata = $store->getStateMetadata('imp');
        self::assertNotNull($metadata);
        self::assertSame(HasCredentialsState::Invalidated, $metadata->state);
        self::assertSame(InvalidationReason::PasswordChanged, $metadata->reason);
        self::assertSame('changed via passwd/', $metadata->detail);
        self::assertNotNull($metadata->since);
    }

    #[Test]
    public function markInvalidatedIsIdempotent(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->set('imp', ['password' => 'secret']);
        $store->markInvalidated('imp', InvalidationReason::BackendRejected);
        $store->markInvalidated('imp', InvalidationReason::AdminForced, 'admin clicked clear');

        $metadata = $store->getStateMetadata('imp');
        self::assertNotNull($metadata);
        self::assertSame(InvalidationReason::AdminForced, $metadata->reason);
        self::assertSame('admin clicked clear', $metadata->detail);
    }

    #[Test]
    public function markNeverHadFlipsStateWithoutReason(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->markNeverHad('imp');

        self::assertSame(HasCredentialsState::NeverHad, $store->getState('imp'));
        $metadata = $store->getStateMetadata('imp');
        self::assertNotNull($metadata);
        self::assertNull($metadata->reason);
        self::assertNull($metadata->detail);
    }

    #[Test]
    public function clearRemovesStateSlots(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->set('imp', ['password' => 'secret']);
        $store->markInvalidated('imp', InvalidationReason::BackendRejected, 'rejected');
        $store->clear('imp');

        // After clear, no metadata is recorded; getState falls back to
        // the implicit NeverHad default.
        self::assertSame(HasCredentialsState::NeverHad, $store->getState('imp'));
        self::assertNull($store->getStateMetadata('imp'));
    }

    #[Test]
    public function getStateMetadataReturnsNullWhenNoSlot(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        // No state has been recorded for this app; metadata is null even
        // though getState() returns NeverHad as the safe default.
        self::assertNull($store->getStateMetadata('imp'));
        self::assertSame(HasCredentialsState::NeverHad, $store->getState('imp'));
    }

    #[Test]
    public function getOrExplainReturnsCredentialsWhenPresent(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->set('horde', ['password' => 'secret']);
        $result = $store->getOrExplain('horde');

        self::assertInstanceOf(CredentialResult::class, $result);
        self::assertSame(HasCredentialsState::Present, $result->state);
        self::assertSame(['password' => 'secret'], $result->credentials);
        self::assertNull($result->reason);
    }

    #[Test]
    public function getOrExplainReturnsInvalidatedReasonWhenInvalidated(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->set('imp', ['password' => 'secret']);
        $store->markInvalidated('imp', InvalidationReason::BackendRejected, 'imap rejected');

        $result = $store->getOrExplain('imp');

        self::assertSame(HasCredentialsState::Invalidated, $result->state);
        self::assertNull($result->credentials);
        self::assertSame(InvalidationReason::BackendRejected, $result->reason);
        self::assertSame('imap rejected', $result->detail);
    }

    #[Test]
    public function getOrExplainReturnsNeverHadByDefault(): void
    {
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $result = $store->getOrExplain('kronolith');

        self::assertSame(HasCredentialsState::NeverHad, $result->state);
        self::assertNull($result->credentials);
        self::assertNull($result->reason);
    }

    #[Test]
    public function setRecoversFromInvalidatedState(): void
    {
        // After a backend rejects credentials and the user re-enters them,
        // a fresh set() should clear the Invalidated state and reason.
        $session = $this->sessionWithBaseApp('horde');
        $store = $this->store($session);

        $store->set('imp', ['password' => 'old']);
        $store->markInvalidated('imp', InvalidationReason::BackendRejected, 'wrong password');
        $store->set('imp', ['password' => 'new']);

        self::assertSame(HasCredentialsState::Present, $store->getState('imp'));
        $metadata = $store->getStateMetadata('imp');
        self::assertNotNull($metadata);
        self::assertNull($metadata->reason, 'reason must be cleared on re-set to Present');
    }
}
