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

/**
 * Interface for session objects that support transparent per-key encryption.
 *
 * Horde encrypts sensitive per-application credentials (auth_app/*) with a
 * secret derived from the user's authentication token. This interface
 * declares the contract for transparent encryption and decryption of
 * individual session values.
 *
 * The encryption service itself is injected into the session via its
 * factory. The library-level Session interface stays unaware of
 * encryption — it sees opaque values.
 */
interface EncryptedValuesInterface
{
    /**
     * Read and decrypt a value.
     *
     * @param string $app  Application scope
     * @param string $name Key name within the scope
     * @return mixed Decrypted value, or null if not set
     */
    public function getEncrypted(string $app, string $name): mixed;

    /**
     * Encrypt and store a value.
     *
     * @param string $app   Application scope
     * @param string $name  Key name within the scope
     * @param mixed  $value Value to encrypt and store
     */
    public function setEncrypted(string $app, string $name, mixed $value): void;

    /**
     * Check whether a key is stored encrypted.
     *
     * @param string $app  Application scope
     * @param string $name Key name within the scope
     */
    public function isEncrypted(string $app, string $name): bool;

    /**
     * Get the full encryption map.
     *
     * @return array<string, true> Composite keys that are stored encrypted
     */
    public function getEncryptionMap(): array;
}
