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
     * Rotate the secret service's key material AND re-encrypt the
     * plaintexts returned by a prior {@see drain()} call — combined
     * form, mutates `$session` in place.
     *
     * @deprecated since 3.2.2 — the combined form mutates the same
     *             HordeSession that drain() read from, which cannot
     *             carry an id rotation (SessionId is immutable). Split
     *             the call: {@see rekey()} on the successor HordeSession,
     *             then {@see refillInto()} on the successor. See
     *             horde/Core#190 and the SessionAccess design note.
     *             The body of this method is now just a composition of
     *             the two new primitives; existing callers see no
     *             functional change.
     *
     * @param list<array{0: string, 1: string, 2: string}> $plainValues
     *     The list returned by drain().
     */
    public function refill(HordeSession $session, array $plainValues): void
    {
        $this->rekey($session);
        $this->refillInto($session, $plainValues);
    }

    /**
     * Point the secret service at `$target` and rotate its key material.
     *
     * The call to {@see SessionSecret::setSession()} tells the secret
     * service to read and write its per-session key from the target's
     * payload instead of any other HordeSession it may have been bound
     * to previously. Under HKDF this is where the derivation gets
     * rebound to the new session id; the derived-key cache is
     * invalidated as a side effect.
     *
     * The subsequent {@see SessionSecret::setKey()} call rotates the
     * key state — under HKDF that means writing a fresh salt to
     * `$target`'s `_secret/salt` slot; under legacy pre-HKDF it minted
     * a new random key. Either way the secret service is now
     * configured to derive/return a key different from the one
     * {@see drain()} used.
     *
     * The encryptor/decryptor closures on `$target` were captured
     * with `$secret` by reference at HordeSession construction time
     * and resolve `getKey()` on every call, so they automatically
     * pick up the new derivation without needing to be replaced.
     */
    public function rekey(HordeSession $target): void
    {
        $this->secret->setSession($target);
        $this->secret->setKey();
    }

    /**
     * Re-encrypt the plaintexts from a prior {@see drain()} call and
     * store them as encrypted slots on `$target`, using the current
     * (post-{@see rekey()}) key.
     *
     * @param list<array{0: string, 1: string, 2: string}> $plainValues
     *     The list returned by drain().
     */
    public function refillInto(HordeSession $target, array $plainValues): void
    {
        $target->refillEncrypted($plainValues);
    }
}
