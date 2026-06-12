<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */

namespace Horde\Core\Session;

use Closure;
use DateTimeImmutable;
use Horde\Injector\Attribute\Factory;
use Horde\SessionHandler\DefaultSession;
use Horde\SessionHandler\Exception\SessionException;
use Horde\SessionHandler\SessionId;
use Horde_Pack;
use Horde_Pack_Exception;

/**
 * Horde-specific session implementation with scoped keys and encryption.
 *
 * Stores data in the same two-level structure as the legacy Horde_Session:
 * $data[$app][$name] for application-scoped values, and $data['_b'],
 * $data['_e'], $data['_r'] for internal metadata. This makes it
 * wire-compatible with sessions created by the legacy handler. Both
 * produce the same $_SESSION layout when exposed through PHP's native
 * session machinery.
 *
 * Encryption closures are optional. Without them, encrypted read returns
 * raw values and encrypted write throws.
 *
 * Carries a pair of runtime intent flags ({@see scheduleRegeneration()},
 * {@see markDestroyed()}). The flags are not data; they are not persisted.
 * The actual lifecycle work (rotating the id, destroying the row, etc.)
 * is the responsibility of {@see SessionLifecycle}, which reads the
 * flags via {@see SessionLifecycle::processFlags()} and clears them via
 * {@see clearLifecycleFlags()} after acting.
 */
#[Factory(factory: HordeSessionFactory::class, method: 'create')]
class HordeSession extends DefaultSession implements SessionMetaInterface, EncryptedValuesInterface
{
    /**
     * Seed used by the session-wide CSRF token derivation
     * ({@see Horde\Token\Token::generate()} via the shim's getToken() and any
     * direct consumer that wants to verify a session-bound CSRF token).
     * Public so consumers can reference it from one place. Per-form tokens
     * (e.g. Horde_Form V3) use their own seed and live in a separate token
     * stream.
     */
    public const CSRF_SEED = 'horde-session-csrf';

    /**
     * Marker prefix for raw string values stored via {@see setScoped()}.
     * A string with this single-byte prefix is unambiguously a stored
     * string; any other byte sequence is either a non-string scalar (raw)
     * or a {@see Horde_Pack} payload.
     */
    public const NOT_SERIALIZED = "\0";

    /** Internal key for session begin timestamp. */
    private const BEGIN_KEY = '_b';

    /** Internal key for the encryption map. */
    private const ENCRYPTED_KEY = '_e';

    /** Internal key for regeneration timestamp. */
    private const REGENERATE_KEY = '_r';

    private ?Closure $encryptor;
    private ?Closure $decryptor;

    /**
     * Lifecycle intent flag set by {@see scheduleRegeneration()}.
     *
     * Runtime-only. Never serialised to the backend. Read by a future
     * SessionPersistenceService during response emission to decide
     * whether to rotate the session id.
     */
    private bool $regenerationScheduled = false;

    /**
     * Lifecycle intent flag set by {@see markDestroyed()}.
     *
     * Runtime-only. Never serialised to the backend. Read by a future
     * SessionPersistenceService and the session middleware during
     * response emission to destroy the backend row and emit a
     * clearing Set-Cookie.
     */
    private bool $destroyed = false;

    /**
     * Pack/unpack service used to serialise arrays, objects, and the
     * plaintext side of encrypted values, matching the wire format
     * produced by the legacy Horde_Session for cross-compatibility.
     */
    private Horde_Pack $pack;

    /**
     * @param SessionId    $id        Session identifier
     * @param array<string, mixed> $data Session data (two-level $_SESSION structure)
     * @param Closure|null $encryptor fn(string $plaintext): string
     * @param Closure|null $decryptor fn(string $ciphertext): string
     */
    public function __construct(
        SessionId $id,
        array $data = [],
        ?Closure $encryptor = null,
        ?Closure $decryptor = null,
    ) {
        parent::__construct($id, $data);
        $this->encryptor = $encryptor;
        $this->decryptor = $decryptor;
        $this->pack = new Horde_Pack();
    }

    // ---------------------------------------------------------------
    // Scoped access: two-level $data[$app][$name]
    // ---------------------------------------------------------------

    /**
     * Get a scoped value.
     *
     * Reverses the wire format applied by {@see setScoped()}:
     * - A string starting with {@see NOT_SERIALIZED} is a stored raw string
     *   minus the marker byte.
     * - A non-string scalar is returned as-is (integers, floats, booleans
     *   and null go on the wire raw).
     * - Any other string is a {@see Horde_Pack} payload and gets unpacked.
     * - If unpacking fails (the value is some other byte sequence), the raw
     *   value is returned to remain robust against unknown shapes.
     */
    public function getScoped(string $app, string $name): mixed
    {
        $raw = $this->data[$app][$name] ?? null;

        if ($raw === null) {
            return null;
        }
        if (!is_string($raw)) {
            return $raw;
        }
        if ($raw === '') {
            return $raw;
        }
        if ($raw[0] === self::NOT_SERIALIZED) {
            return substr($raw, 1);
        }

        try {
            return $this->pack->unpack($raw);
        } catch (Horde_Pack_Exception) {
            return $raw;
        }
    }

    /**
     * Set a scoped value.
     *
     * Applies the wire format last week's Horde_Session produced:
     * - Strings get a single-byte {@see NOT_SERIALIZED} prefix.
     * - Arrays and objects get serialised via {@see Horde_Pack} (with
     *   `phpob` opt set for objects).
     * - Other scalars (int, float, bool, null) go on the wire raw.
     */
    public function setScoped(string $app, string $name, mixed $value): void
    {
        $stored = $this->encodeForStorage($value);

        if (!isset($this->data[$app])) {
            $this->data[$app] = [];
        }
        $this->data[$app][$name] = $stored;
        $this->dirty = true;
    }

    /**
     * Encode a value for storage in the scoped or encrypted-plaintext
     * positions, matching the legacy Horde_Session wire format.
     */
    private function encodeForStorage(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::NOT_SERIALIZED . $value;
        }
        if (is_array($value) || is_object($value)) {
            $opts = ['compress' => 0];
            if (is_object($value)) {
                $opts['phpob'] = true;
            }
            return $this->pack->pack($value, $opts);
        }

        return $value;
    }

    /**
     * Check if a scoped key exists.
     */
    public function hasScoped(string $app, string $name): bool
    {
        return isset($this->data[$app]) && array_key_exists($name, $this->data[$app]);
    }

    /**
     * Remove a scoped key.
     */
    public function removeScoped(string $app, string $name): void
    {
        if (isset($this->data[$app]) && array_key_exists($name, $this->data[$app])) {
            unset($this->data[$app][$name]);
            $this->dirty = true;

            // Clean up empty app scope
            if (empty($this->data[$app])) {
                unset($this->data[$app]);
            }
        }

        // Also remove from encryption map
        if (isset($this->data[self::ENCRYPTED_KEY][$app][$name])) {
            unset($this->data[self::ENCRYPTED_KEY][$app][$name]);
            if (empty($this->data[self::ENCRYPTED_KEY][$app])) {
                unset($this->data[self::ENCRYPTED_KEY][$app]);
            }
            $this->dirty = true;
        }
    }

    /**
     * Get all key names for a given application scope.
     *
     * @return array<string> Key names within the app scope
     */
    public function keysForApp(string $app): array
    {
        if (!isset($this->data[$app]) || !is_array($this->data[$app])) {
            return [];
        }

        return array_keys($this->data[$app]);
    }

    // ---------------------------------------------------------------
    // SessionMetaInterface
    // ---------------------------------------------------------------

    public function getAuthenticatedUser(): ?string
    {
        $value = $this->data['horde']['auth/userId'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function getAuthId(): ?string
    {
        $value = $this->data['horde']['auth/authId'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function getBrowserFingerprint(): ?string
    {
        $value = $this->data['horde']['auth/browser'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function getRemoteAddress(): ?string
    {
        $value = $this->data['horde']['auth/remoteAddr'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function getAuthTimestamp(): ?DateTimeImmutable
    {
        $value = $this->data['horde']['auth/timestamp'] ?? null;

        if (!is_int($value)) {
            return null;
        }

        return (new DateTimeImmutable())->setTimestamp($value);
    }

    public function getSessionBegin(): ?DateTimeImmutable
    {
        $value = $this->data[self::BEGIN_KEY] ?? null;

        if (!is_int($value)) {
            return null;
        }

        return (new DateTimeImmutable())->setTimestamp($value);
    }

    /**
     * Record the session-begin timestamp.
     *
     * Writes the {@see BEGIN_KEY} slot at the top level as a bare int.
     * Pairs with {@see getSessionBegin}, which reads the same shape.
     * Use this in preference to {@see set()} so the begin slot's wire
     * format stays internal to this class.
     *
     * Idempotent and dirty-marking. The legacy `Horde_Session` shim
     * and {@see SessionLifecycle::initialiseTimestamps} both call this
     * to converge on a single shape across all writers.
     */
    public function setSessionBegin(int $timestamp): void
    {
        $this->data[self::BEGIN_KEY] = $timestamp;
        $this->dirty = true;
    }

    /**
     * Read the session-id regeneration deadline as a Unix timestamp,
     * or null when no deadline has been recorded.
     *
     * Pairs with {@see setRegenerationDeadline}. The slot is a top-level
     * int. Pre-fix code wrote the deadline via
     * `setScoped(REGENERATE_KEY, '', $ts)` which produced
     * `$data['_r']['']` and was internally consistent (same writer used
     * the same getScoped reader) but inconsistent with `_b`. Both keys
     * now use the same top-level int shape.
     */
    public function getRegenerationDeadline(): ?int
    {
        $value = $this->data[self::REGENERATE_KEY] ?? null;
        return is_int($value) ? $value : null;
    }

    /**
     * Record the session-id regeneration deadline.
     *
     * Writes the {@see REGENERATE_KEY} slot at the top level as a bare
     * int. Pairs with {@see getRegenerationDeadline}.
     */
    public function setRegenerationDeadline(int $timestamp): void
    {
        $this->data[self::REGENERATE_KEY] = $timestamp;
        $this->dirty = true;
    }

    public function getAuthenticatedApps(): array
    {
        $appData = $this->data['horde'] ?? [];
        if (!is_array($appData)) {
            return [];
        }

        $prefix = 'auth_app/';
        $prefixLen = strlen($prefix);
        $apps = [];

        foreach (array_keys($appData) as $key) {
            if (str_starts_with($key, $prefix)) {
                $apps[] = substr($key, $prefixLen);
            }
        }

        return $apps;
    }

    // ---------------------------------------------------------------
    // EncryptedValuesInterface
    // ---------------------------------------------------------------

    public function getEncrypted(string $app, string $name): mixed
    {
        $raw = $this->data[$app][$name] ?? null;

        if ($raw === null) {
            return null;
        }

        if ($this->decryptor === null) {
            return $raw;
        }

        if (!is_string($raw)) {
            return $raw;
        }

        $decrypted = ($this->decryptor)($raw);

        try {
            return $this->pack->unpack($decrypted);
        } catch (Horde_Pack_Exception) {
            return $decrypted;
        }
    }

    public function setEncrypted(string $app, string $name, mixed $value): void
    {
        if ($this->encryptor === null) {
            throw new SessionException('No encryptor configured. Cannot store encrypted value.');
        }

        $opts = ['compress' => 0];
        if (is_object($value)) {
            $opts['phpob'] = true;
        }
        $packed = $this->pack->pack($value, $opts);
        $encrypted = ($this->encryptor)($packed);

        // Store the encrypted value
        if (!isset($this->data[$app])) {
            $this->data[$app] = [];
        }
        $this->data[$app][$name] = $encrypted;

        // Track in encryption map: $_SESSION['_e'][$app][$name] = true
        if (!isset($this->data[self::ENCRYPTED_KEY])) {
            $this->data[self::ENCRYPTED_KEY] = [];
        }
        if (!isset($this->data[self::ENCRYPTED_KEY][$app])) {
            $this->data[self::ENCRYPTED_KEY][$app] = [];
        }
        $this->data[self::ENCRYPTED_KEY][$app][$name] = true;
        $this->dirty = true;
    }

    public function isEncrypted(string $app, string $name): bool
    {
        return isset($this->data[self::ENCRYPTED_KEY][$app][$name]);
    }

    /** @return array<string, array<string, true>> */
    public function getEncryptionMap(): array
    {
        $map = $this->data[self::ENCRYPTED_KEY] ?? [];

        return is_array($map) ? $map : [];
    }

    // ---------------------------------------------------------------
    // Re-encryption support (session ID regeneration)
    // ---------------------------------------------------------------

    /**
     * Replace encryption closures and re-encrypt all encrypted values.
     *
     * Used during session ID regeneration: the old key is still available
     * via the current decryptor. After replacing the closures, all values
     * tracked in the encryption map are decrypted with the old decryptor
     * and re-encrypted with the new encryptor.
     */
    public function updateEncryptionCallbacks(Closure $encryptor, Closure $decryptor): void
    {
        $map = $this->data[self::ENCRYPTED_KEY] ?? [];
        if (!is_array($map)) {
            $map = [];
        }

        // Decrypt all encrypted values with the old decryptor
        $plainValues = [];
        foreach ($map as $app => $names) {
            if (!is_array($names)) {
                continue;
            }
            foreach (array_keys($names) as $name) {
                $raw = $this->data[$app][$name] ?? null;
                if ($raw !== null && is_string($raw) && $this->decryptor !== null) {
                    $decrypted = ($this->decryptor)($raw);
                    $plainValues[] = [$app, $name, $decrypted];
                }
            }
        }

        // Replace closures
        $this->encryptor = $encryptor;
        $this->decryptor = $decryptor;

        // Re-encrypt with the new encryptor
        foreach ($plainValues as [$app, $name, $serialized]) {
            $encrypted = ($this->encryptor)($serialized);
            $this->data[$app][$name] = $encrypted;
            $this->dirty = true;
        }
    }

    // ---------------------------------------------------------------
    // Lifecycle intent flags
    //
    // Runtime-only state set by login / logout flows to signal that
    // the session id should be rotated, or that the session row
    // should be destroyed, when the response is emitted. The flags
    // are read by a future SessionPersistenceService and the session
    // middleware; setting a flag has no immediate side effect.
    //
    // Never serialised to the backend. Always start as false in a
    // fresh HordeSession instance, including when the factory's
    // restore() path rebuilds an instance from a stored payload.
    // ---------------------------------------------------------------

    /**
     * Mark the session as needing a fresh ID.
     *
     * Called by login flows after successful authentication to defeat
     * session fixation. The actual rotation is performed downstream by
     * SessionPersistenceService during response emission. Setting this
     * flag does NOT change the session ID immediately.
     *
     * Idempotent.
     */
    public function scheduleRegeneration(): void
    {
        $this->regenerationScheduled = true;
    }

    /**
     * Whether {@see scheduleRegeneration()} has been called this request.
     */
    public function shouldRegenerate(): bool
    {
        return $this->regenerationScheduled;
    }

    /**
     * Mark the session for destruction.
     *
     * Called by logout flows. The actual destruction (backend row delete
     * plus clearing Set-Cookie) is performed downstream by
     * SessionPersistenceService during response emission. Setting this
     * flag does NOT clear the data immediately; readers continue to see
     * whatever was in scope before the call until the response phase
     * runs.
     *
     * Idempotent.
     */
    public function markDestroyed(): void
    {
        $this->destroyed = true;
    }

    /**
     * Whether {@see markDestroyed()} has been called this request.
     */
    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    /**
     * Clear both lifecycle intent flags.
     *
     * @internal Called by lifecycle engines (currently
     *           {@see SessionLifecycle}) after they have acted on the
     *           markers. Application code should not call this directly;
     *           intent setters set, executors clear.
     */
    public function clearLifecycleFlags(): void
    {
        $this->regenerationScheduled = false;
        $this->destroyed = false;
    }
}
