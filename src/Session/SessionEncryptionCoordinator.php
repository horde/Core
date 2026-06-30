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

namespace Horde\Core\Session;

use Horde\Core\Secret\SessionSecret;

/**
 * Mediates session-payload re-encryption with secret-service key
 * rotation around an externally-driven session id rotation.
 *
 * Called by any code that rotates the session id, since under the
 * HKDF derivation the session id is one of the inputs to the per-
 * session encryption key. Rotating the id without notifying the
 * encryption layer would leave the existing ciphertext bound to the
 * old derivation and therefore unreadable on the next encrypted-slot
 * access.
 *
 * **Decoupling.** Neither {@see HordeSession} nor {@see SessionSecret}
 * import each other. This class is the only place in the codebase that
 * knows both. Callers that perform id rotation (legacy
 * {@see SessionLifecycle::regenerate()}, modern
 * {@see \Horde\Core\Middleware\HordeSessionMiddleware::finaliseRegenerated()})
 * import only this coordinator, not the secret service.
 *
 * **Two-phase rotation.** The coordinator exposes a split
 * {@see drain()} / {@see refill()} API rather than a single
 * orchestrated method. The split is necessary because the legacy
 * stack and the modern stack rotate the session id with different
 * primitives (`session_regenerate_id(true)` vs
 * `SessionHandler::regenerate()`), and both have to happen BETWEEN
 * drain and refill so the drain reads under the old key and the
 * refill writes under the new derived key.
 *
 * **Failure semantics.** If the caller's id-rotation step throws,
 * the coordinator leaves the session in the drained-but-not-refilled
 * state. Callers are responsible for either calling
 * {@see refill()} on the original session (preserving the pre-
 * rotation state) or letting the exception propagate. The modern
 * middleware's `finaliseRegenerated()` falls back to the steady
 * persistence path with the original session on rotation failure,
 * which means it refills against the original.
 *
 * **Lifetime.** Per-request singleton, bound in the DI container.
 */
final class SessionEncryptionCoordinator
{
    public function __construct(
        private readonly SessionSecret $secret,
    ) {}

    /**
     * Pull every encrypted slot in the session into plaintext under
     * the current (pre-rotation) key.
     *
     * The session's encrypted-slot ciphertexts are left in place if
     * decryption succeeded; slots that failed to decrypt are dropped
     * from the session payload as a side effect of
     * {@see HordeSession::drainEncrypted()}. The caller must follow
     * this with {@see refill()} (or accept that the slots stay bound
     * to the old key) to complete the rotation.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     *     Opaque plaintext list. Pass back into {@see refill()}.
     */
    public function drain(HordeSession $session): array
    {
        return $session->drainEncrypted();
    }

    /**
     * Rotate the secret service's key material and re-encrypt the
     * plaintexts returned by a prior {@see drain()} call.
     *
     * The call to {@see SessionSecret::setKey()} updates the secret
     * service's key state. Under HKDF this means writing a fresh
     * salt to the session payload; under the legacy pre-HKDF code
     * this minted a new random key. Either way the secret service
     * is now configured to derive/return a key different from the
     * one drain() used.
     *
     * @param list<array{0: string, 1: string, 2: string}> $plainValues
     *     The list returned by drain().
     */
    public function refill(HordeSession $session, array $plainValues): void
    {
        $this->secret->setKey();
        $session->refillEncrypted($plainValues);
    }
}
