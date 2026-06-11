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
}
