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

use Horde\Core\Session\HordeSession;

/**
 * Session-key cipher used by {@see \Horde\Core\Session\SessionLifecycle}
 * to re-key encrypted session payloads on `clean()` / `destroy()`.
 *
 * Mirrors the two methods on legacy `Horde_Secret` that the lifecycle
 * actually calls. Exists so DI consumers can name the contract by a
 * type-hintable interface rather than the bare-string legacy binding
 * `Horde_Secret_Cbc`. The legacy class hierarchy
 * (`Horde_Core_Secret_Cbc extends Horde_Core_Secret extends Horde_Secret`)
 * does not extend `Horde_Secret_Cbc`, so a `?Horde_Secret_Cbc`
 * type hint rejects the real instance the injector returns.
 *
 * Implementations: {@see \Horde_Core_Secret_Cbc}.
 *
 * @internal Until other consumers materialise. The contract is
 *           stable but the namespace may move when the broader
 *           Secret stack modernises.
 */
interface SessionSecret
{
    /**
     * Set or rotate the per-session encryption key.
     *
     * Untyped on purpose: matches the legacy `Horde_Secret::setKey()`
     * signature so existing implementations satisfy this interface
     * without modification.
     *
     * @param string $keyname Logical name of the key to set.
     * @return mixed Whatever the underlying implementation returns
     *               (legacy returns the generated key string).
     */
    public function setKey($keyname = 'generic');

    /**
     * Clear the per-session encryption key.
     *
     * Untyped on purpose: matches the legacy
     * `Horde_Secret::clearKey()` signature.
     *
     * @param string $keyname Logical name of the key to clear.
     * @return mixed Whatever the underlying implementation returns
     *               (legacy returns bool).
     */
    public function clearKey($keyname = 'generic');

    /**
     * Wire the modern session into this secret service.
     *
     * After this call, the implementation MUST read and write the
     * per-session key in the session payload (e.g. via
     * {@see HordeSession::getScoped()} / {@see HordeSession::setScoped()}
     * at a slot of its choosing) instead of relying exclusively on a
     * parallel cookie. Implementations MAY keep the legacy cookie path
     * as a backwards-compatible fallback and SHOULD migrate any value
     * found in the cookie into the session payload on first read.
     *
     * Called by {@see \Horde\Core\Session\HordeSessionFactory::create()}
     * right after the modern session is built; subsequent encrypted
     * reads and writes therefore land on a session that already knows
     * how to source its key without depending on the user's cookie jar.
     */
    public function setSession(HordeSession $session): void;
}
