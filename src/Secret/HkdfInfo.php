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

namespace Horde\Core\Secret;

/**
 * Registry of HKDF `info` parameters used by Horde\Core's session
 * encryption layer.
 *
 * Each constant is a string baked into the key derivation for one
 * (algorithm, purpose, generation) tuple. The `info` parameter in HKDF
 * (RFC 5869) provides domain separation: two derivations with the same
 * input keying material and salt but different `info` produce
 * unrelated key material.
 *
 * **Discipline:**
 *
 * - Retired constants are NEVER removed from this class. Removing one
 *   would lock out every session whose ciphertext is bound to the old
 *   derivation.
 * - Retired constants are NEVER reused. Two unrelated purposes that
 *   accidentally share an `info` value cross-contaminate the keys.
 * - New constants follow the naming convention
 *   `<realm>-<scope>-<algorithm>-v<generation>`, e.g.
 *   `'horde-session-blowfish-cbc-v1'`. Each segment is independent:
 *   * `realm` — vendor/product (always `horde` here).
 *   * `scope` — what subsystem the derivation belongs to.
 *   * `algorithm` — the cipher the derived key feeds into.
 *   * `generation` — bumps when ANY input to the derivation changes
 *     (hash function, ikm assembly order, salt source, output length).
 *
 * **When to add a new constant:**
 *
 * Add a new constant (with v{N+1}) whenever the derivation algorithm
 * changes in a way that means old ciphertext cannot be decrypted by
 * code using the new constant. Examples:
 *
 * - Switching the hash function from SHA-256 to SHA-3.
 * - Changing the order of inputs in the `ikm` concatenation.
 * - Changing what the `salt` parameter is sourced from.
 * - Changing the output length or the downstream cipher.
 *
 * Do NOT bump on input rotation (a fresh session salt or rotated
 * `$conf['secret_key']` is not an algorithm change; it's an input
 * change).
 *
 * Implementations swapping to a new generation typically read both
 * constants for one or more transitional minor releases (write under
 * v{N+1}, fall back to v{N} on read), then drop the older read path
 * after the migration window.
 */
final class HkdfInfo
{
    /**
     * Session-scope Blowfish/CBC key, generation 1.
     *
     * Derivation: HKDF-SHA256 over `$conf['secret_key'] . session.salt`,
     * salted with `session.id`, expanded to 56 bytes (Blowfish maximum
     * key size).
     *
     * Used by {@see \Horde_Core_Secret_Cbc::getKey()} to derive the
     * per-session Blowfish key from the operator's master secret, the
     * per-session salt in the session payload, and the current
     * `session.id`.
     */
    public const SESSION_BLOWFISH_CBC_V1 = 'horde-session-blowfish-cbc-v1';

    /**
     * Block instantiation. This class is a registry of constants and
     * is never instantiated.
     */
    private function __construct()
    {
    }
}
