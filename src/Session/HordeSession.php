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

/**
 * Horde-specific session implementation with scoped keys and encryption.
 *
 * Stores data in the same two-level structure as the legacy Horde_Session:
 * $data[$app][$name] for application-scoped values, and $data['_b'],
 * $data['_e'], $data['_r'] for internal metadata. This makes it
 * wire-compatible with sessions created by the legacy handler — both
 * produce the same $_SESSION layout when exposed through PHP's native
 * session machinery.
 *
 * Encryption closures are optional. Without them, encrypted read returns
 * raw values and encrypted write throws.
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

    /** Internal key for session begin timestamp. */
    private const BEGIN_KEY = '_b';

    /** Internal key for the encryption map. */
    private const ENCRYPTED_KEY = '_e';

    /** Internal key for regeneration timestamp. */
    private const REGENERATE_KEY = '_r';

    private ?Closure $encryptor;
    private ?Closure $decryptor;

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
    }

    // ---------------------------------------------------------------
    // Scoped access — two-level $data[$app][$name]
    // ---------------------------------------------------------------

    /**
     * Get a scoped value.
     */
    public function getScoped(string $app, string $name): mixed
    {
        return $this->data[$app][$name] ?? null;
    }

    /**
     * Set a scoped value.
     */
    public function setScoped(string $app, string $name, mixed $value): void
    {
        if (!isset($this->data[$app])) {
            $this->data[$app] = [];
        }
        $this->data[$app][$name] = $value;
        $this->dirty = true;
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

        return unserialize($decrypted);
    }

    public function setEncrypted(string $app, string $name, mixed $value): void
    {
        if ($this->encryptor === null) {
            throw new SessionException('No encryptor configured — cannot store encrypted value');
        }

        $serialized = serialize($value);
        $encrypted = ($this->encryptor)($serialized);

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
}
