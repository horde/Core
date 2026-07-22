<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Auth;

use DateTimeImmutable;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionAccess;
use Horde\Injector\Attribute\Factory;
use Horde\Injector\Injector;

/**
 * Persistence layer for authenticated-user credentials.
 *
 * Owns the legacy `('horde', 'auth_app/<app>')` slot pair: the encrypted
 * credentials map and the `auth_app_init/<app>` companion flag. Reads and
 * writes go through {@see HordeSession::getEncrypted()} /
 * {@see HordeSession::setEncrypted()} so the on-disk wire format matches
 * what the legacy `Horde_Session::set(..., ENCRYPT)` path produced.
 *
 * Modern callers consume this store directly. Legacy callers go through
 * `Horde_Registry::setAuthCredential`/`getAuthCredential` which delegate
 * here. Both flows produce byte-identical writes at the same slot.
 *
 * The class deliberately does NOT touch:
 * - `('horde', 'auth/credentials')`: Registry's base-app pointer. Registry
 *   continues to write that slot in `setAuth()`. The store only reads it.
 * - Registry's per-app authentication caches (`_cache['existing']`,
 *   `_cache['isauth']`): those are Registry-internal state. The Registry
 *   facade clears them after delegating the persistence here.
 */
#[Factory(factory: AuthCredentialStoreFactory::class, method: 'create')]
class AuthCredentialStore
{
    /** Session scope under which auth slots live. */
    private const SCOPE = 'horde';

    /** Slot key prefix for encrypted credentials. */
    private const CREDENTIALS_PREFIX = 'auth_app/';

    /** Slot key prefix for the init flag. */
    private const INIT_PREFIX = 'auth_app_init/';

    /** Slot key prefix for {@see HasCredentialsState} per app. */
    private const STATE_PREFIX = 'auth_app_state/';

    /** Slot key prefix for the int timestamp of the last state transition. */
    private const STATE_AT_PREFIX = 'auth_app_state_at/';

    /** Slot key prefix for {@see InvalidationReason} when state is Invalidated. */
    private const STATE_REASON_PREFIX = 'auth_app_state_reason/';

    /** Slot key prefix for optional free-text detail accompanying the reason. */
    private const STATE_DETAIL_PREFIX = 'auth_app_state_detail/';

    /** Slot key for the base-app pointer (read-only here). */
    private const BASE_APP_KEY = 'auth/credentials';

    public function __construct(
        private readonly SessionAccess $session,
    ) {}

    /**
     * Persist a credentials array for the given app.
     *
     * Implements the legacy dedup-against-base-app rule: when the entry being
     * stored matches what is already stored at the base app's slot, persist
     * `true` instead of duplicating the array. Callers reading via
     * {@see get()} get the materialised credentials regardless.
     *
     * @param string             $app         App scope. When null, falls back
     *                                        to the base app stored under
     *                                        `auth/credentials`.
     * @param array<string,mixed> $credentials Full credentials array.
     */
    public function set(?string $app, array $credentials): void
    {
        $baseApp = $this->baseApp();

        $entry = $credentials;
        if ($baseApp !== null) {
            $existingForBase = $this->session->getEncrypted(
                self::SCOPE,
                self::CREDENTIALS_PREFIX . $baseApp,
            );
            if ($existingForBase == $entry) {
                $entry = true;
            }
        }

        if ($app === null) {
            $app = $baseApp;
        }

        if ($app === null) {
            // No base app and no explicit app: nothing to anchor against.
            return;
        }

        if ($entry === true) {
            $this->session->setScoped(
                self::SCOPE,
                self::CREDENTIALS_PREFIX . $app,
                true,
            );
        } else {
            $this->session->setEncrypted(
                self::SCOPE,
                self::CREDENTIALS_PREFIX . $app,
                $entry,
            );
        }
        $this->session->setScoped(self::SCOPE, self::INIT_PREFIX . $app, true);
        $this->writeState($app, HasCredentialsState::Present);
    }

    /**
     * Persist a credentials array for the given app.
     *
     * Preferred name. Forwards to {@see set()} which keeps its existing
     * signature for legacy callers (Registry) but performs the same work
     * including marking the state as {@see HasCredentialsState::Present}.
     *
     * @param array<string,mixed> $credentials
     */
    public function setCredentials(string $app, array $credentials): void
    {
        $this->set($app, $credentials);
    }

    /**
     * Update a single credential within the given app's credentials array.
     *
     * Reads the current credentials, sets the named key to the supplied value,
     * and persists the result via {@see set()}. Returns false if no base app
     * is established (i.e. the user is not authenticated through Registry).
     */
    public function setOne(?string $app, string $credential, mixed $value): bool
    {
        $current = $this->get($app);
        if ($current === false) {
            return false;
        }

        if (!is_array($current)) {
            $current = [];
        }
        $current[$credential] = $value;

        $this->set($app, $current);
        return true;
    }

    /**
     * Recover the credentials array for the given app.
     *
     * Returns false when no base app has been established (no authenticated
     * user per Registry's contract). Returns the credentials array when the
     * slot is present. Falls back to the base app's slot when the requested
     * app's slot is absent, matching the legacy `_getAuthCredentials`
     * resolver.
     *
     * @return array<string,mixed>|false
     */
    public function get(?string $app): array|bool
    {
        $baseApp = $this->baseApp();
        if ($baseApp === null) {
            return false;
        }

        if ($app === null) {
            $app = $baseApp;
        }

        $slotKey = self::CREDENTIALS_PREFIX . $app;
        if (!$this->session->hasScoped(self::SCOPE, $slotKey)) {
            // Per legacy semantics, fall back to the base app's slot once.
            return $app !== $baseApp ? $this->get($baseApp) : false;
        }

        // The dedup rule stores `true` to mean "same credentials as base app".
        // Materialise that here so callers always see an array.
        if ($this->session->isEncrypted(self::SCOPE, $slotKey)) {
            $value = $this->session->getEncrypted(self::SCOPE, $slotKey);
        } else {
            $value = $this->session->getScoped(self::SCOPE, $slotKey);
        }

        if ($value === true) {
            // Resolve the dedup marker once. If app *is* the base app, the
            // marker is corrupt (it would point at itself); treat as missing.
            return $app !== $baseApp ? $this->get($baseApp) : false;
        }

        if (is_array($value)) {
            return $value;
        }

        // Wrong encryption key, legacy wire format, or otherwise corrupted
        // slot data. Treat as missing credentials rather than violating the
        // declared array|false return type.
        return false;
    }

    /**
     * Clear the stored credentials and init flag for the given app.
     *
     * Mirrors the slot removals `Horde_Registry::clearAuthApp` makes on logout.
     * Also clears the per-app state slots so a subsequent {@see getState()}
     * read returns {@see HasCredentialsState::NeverHad} (the implicit
     * default) rather than reporting stale state.
     */
    public function clear(string $app): void
    {
        $this->session->removeScoped(
            self::SCOPE,
            self::CREDENTIALS_PREFIX . $app,
        );
        $this->session->removeScoped(self::SCOPE, self::INIT_PREFIX . $app);
        $this->session->removeScoped(self::SCOPE, self::STATE_PREFIX . $app);
        $this->session->removeScoped(self::SCOPE, self::STATE_AT_PREFIX . $app);
        $this->session->removeScoped(self::SCOPE, self::STATE_REASON_PREFIX . $app);
        $this->session->removeScoped(self::SCOPE, self::STATE_DETAIL_PREFIX . $app);
    }

    /**
     * Whether the given app has been initialised (auth_app_init/<app> = true).
     */
    public function isInitialized(string $app): bool
    {
        return $this->session->hasScoped(self::SCOPE, self::INIT_PREFIX . $app)
            && $this->session->getScoped(self::SCOPE, self::INIT_PREFIX . $app) === true;
    }

    /**
     * Resolve the base app stored at `auth/credentials`. Registry writes this
     * during `setAuth()`. Returns null when nothing is set, matching the
     * legacy `_getAuthCredentials` precondition.
     */
    private function baseApp(): ?string
    {
        $value = $this->session->getScoped(self::SCOPE, self::BASE_APP_KEY);

        return is_string($value) ? $value : null;
    }

    // ---------------------------------------------------------------
    // Per-app credential state (HasCredentialsState + InvalidationReason)
    //
    // The legacy slot pair (`auth_app/<app>` + `auth_app_init/<app>`) only
    // distinguishes "credentials present" from "absent". The state slots
    // below add a third distinction: "absent because invalidated" with a
    // typed reason. Apps that need to surface a precise reauth prompt
    // (IMP after IMAP rejects the password, etc.) read via
    // {@see getOrExplain()} or {@see getState()}.
    // ---------------------------------------------------------------

    /**
     * Mark the app's credentials as invalidated.
     *
     * Removes the cleartext from the encrypted slot (the credentials are
     * no longer trustworthy) but keeps the per-app metadata so callers
     * see {@see HasCredentialsState::Invalidated} with a reason on the
     * next read.
     *
     * Idempotent: a second call updates the timestamp and reason without
     * raising.
     */
    public function markInvalidated(
        string $app,
        InvalidationReason $reason,
        ?string $detail = null,
    ): void {
        $this->session->removeScoped(self::SCOPE, self::CREDENTIALS_PREFIX . $app);
        $this->writeState($app, HasCredentialsState::Invalidated, $reason, $detail);
    }

    /**
     * Mark the app's credentials as never having been provided this
     * request, e.g. when {@see RememberMeMiddleware} mints a session
     * from a remember-me token.
     *
     * Equivalent to clearing the slot but signposts the absence as
     * deliberate (the user IS identified) rather than as a never-touched
     * default.
     *
     * Most callers can rely on the implicit default: an absent
     * `auth_app_state/<app>` slot is read as {@see HasCredentialsState::NeverHad}.
     * Calling this method is useful when the caller wants to record the
     * timestamp of the transition for audit purposes.
     */
    public function markNeverHad(string $app): void
    {
        $this->session->removeScoped(self::SCOPE, self::CREDENTIALS_PREFIX . $app);
        $this->writeState($app, HasCredentialsState::NeverHad);
    }

    /**
     * Read the current per-app credential state.
     *
     * Returns {@see HasCredentialsState::NeverHad} when no state has
     * been recorded for the app: that's the safe default for
     * unknown / absent state. Apps that need to distinguish that from
     * an explicit "never had" intent should consult
     * {@see getStateMetadata()} for the timestamp.
     */
    public function getState(string $app): HasCredentialsState
    {
        $raw = $this->session->getScoped(self::SCOPE, self::STATE_PREFIX . $app);
        if (!is_string($raw)) {
            return HasCredentialsState::NeverHad;
        }
        return HasCredentialsState::tryFrom($raw) ?? HasCredentialsState::NeverHad;
    }

    /**
     * Read the per-app credential state plus its transition metadata.
     *
     * Returns null when no state has been recorded. The implicit
     * "absent = NeverHad" inference applied by {@see getState()} does
     * NOT apply here: callers asking for metadata want to know whether
     * a transition happened, and a missing slot means it didn't.
     */
    public function getStateMetadata(string $app): ?CredentialStateMetadata
    {
        $rawState = $this->session->getScoped(self::SCOPE, self::STATE_PREFIX . $app);
        if (!is_string($rawState)) {
            return null;
        }
        $state = HasCredentialsState::tryFrom($rawState);
        if ($state === null) {
            return null;
        }

        $rawAt = $this->session->getScoped(self::SCOPE, self::STATE_AT_PREFIX . $app);
        $since = is_int($rawAt)
            ? (new DateTimeImmutable())->setTimestamp($rawAt)
            : null;

        $rawReason = $this->session->getScoped(self::SCOPE, self::STATE_REASON_PREFIX . $app);
        $reason = is_string($rawReason)
            ? InvalidationReason::tryFrom($rawReason)
            : null;

        $rawDetail = $this->session->getScoped(self::SCOPE, self::STATE_DETAIL_PREFIX . $app);
        $detail = is_string($rawDetail) ? $rawDetail : null;

        return new CredentialStateMetadata(
            state: $state,
            since: $since,
            reason: $reason,
            detail: $detail,
        );
    }

    /**
     * Read the current credentials with explanatory state context.
     *
     * Designed for `match` over the returned `state`: callers that need
     * the credentials get them when the state is
     * {@see HasCredentialsState::Present}, and otherwise have the
     * reason context they need to render the right reauth UX.
     *
     * Falls back to the legacy {@see get()} resolver to materialise
     * credentials so the dedup-against-base-app rule keeps working.
     * Returns a CredentialResult with `credentials = null` when the
     * resolver returns false.
     */
    public function getOrExplain(string $app): CredentialResult
    {
        $value = $this->get($app);
        $state = $this->getState($app);

        if ($state === HasCredentialsState::Present) {
            // The legacy resolver may still return false (no base app,
            // or stale slot). Fall through to NeverHad in that case.
            if (is_array($value)) {
                return new CredentialResult(
                    state: HasCredentialsState::Present,
                    credentials: $value,
                );
            }
            return new CredentialResult(state: HasCredentialsState::NeverHad);
        }

        $metadata = $this->getStateMetadata($app);
        return new CredentialResult(
            state: $state,
            reason: $metadata?->reason,
            detail: $metadata?->detail,
        );
    }

    /**
     * Write the per-app state slots.
     */
    private function writeState(
        string $app,
        HasCredentialsState $state,
        ?InvalidationReason $reason = null,
        ?string $detail = null,
    ): void {
        $this->session->setScoped(self::SCOPE, self::STATE_PREFIX . $app, $state->value);
        $this->session->setScoped(self::SCOPE, self::STATE_AT_PREFIX . $app, time());

        if ($reason !== null) {
            $this->session->setScoped(self::SCOPE, self::STATE_REASON_PREFIX . $app, $reason->value);
        } else {
            $this->session->removeScoped(self::SCOPE, self::STATE_REASON_PREFIX . $app);
        }

        if ($detail !== null && $detail !== '') {
            $this->session->setScoped(self::SCOPE, self::STATE_DETAIL_PREFIX . $app, $detail);
        } else {
            $this->session->removeScoped(self::SCOPE, self::STATE_DETAIL_PREFIX . $app);
        }
    }
}
