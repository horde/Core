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

use Horde\Core\Session\HordeSession;
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
 * - `('horde', 'auth/credentials')` — Registry's base-app pointer. Registry
 *   continues to write that slot in `setAuth()`. The store only reads it.
 * - Registry's per-app authentication caches (`_cache['existing']`,
 *   `_cache['isauth']`) — those are Registry-internal state. The Registry
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

    /** Slot key for the base-app pointer (read-only here). */
    private const BASE_APP_KEY = 'auth/credentials';

    public function __construct(
        private readonly HordeSession $session,
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
            // No base app and no explicit app — nothing to anchor against.
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
     * app's slot is absent — matching the legacy `_getAuthCredentials`
     * resolver.
     *
     * @return array<string,mixed>|true|false
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

        if ($value === true && $app !== $baseApp) {
            return $this->get($baseApp);
        }

        return is_array($value) ? $value : $value;
    }

    /**
     * Clear the stored credentials and init flag for the given app.
     *
     * Mirrors the slot removals `Horde_Registry::clearAuthApp` makes on logout.
     */
    public function clear(string $app): void
    {
        $this->session->removeScoped(
            self::SCOPE,
            self::CREDENTIALS_PREFIX . $app,
        );
        $this->session->removeScoped(self::SCOPE, self::INIT_PREFIX . $app);
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
}
