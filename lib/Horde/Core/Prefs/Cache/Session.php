<?php

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2010-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionAccess;

/**
 * Cache storage implementation using {@see HordeSession} through a
 * request-scoped {@see SessionAccess} slot.
 *
 * Reads go through the modern PSR-4 {@see HordeSession} via
 * {@see SessionAccess} so post-regenerate values reach us on the next
 * call (horde/Core#190). Writes invalidate the cached scope instead of
 * replacing it, so the next request reloads the scope from storage.
 * This avoids a write-ordering race against
 * {@see Horde\Core\Session\SessionLifecycle::shutdown()}, which mirrors
 * {@see HordeSession} back into `$_SESSION` at request shutdown: a
 * `store()` that ran after the mirror would never reach the persisted
 * session row, leaving subsequent requests with stale prefs until the
 * session was destroyed.
 *
 * The wire format diverges from values previously written through the
 * legacy `Horde_Session::set()` path. Stale entries from the prior format
 * are treated as missing on the first read after deploy.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @author     Ralf Lang <ralf.lang@ralf-lang.de>
 * @category   Horde
 * @copyright  2010-2026 Horde LLC
 * @deprecated Use Horde_Prefs_Cache_HordeCache with the
 *             Horde_Core_Cache_Session driver instead.
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Core
 */
class Horde_Core_Prefs_Cache_Session extends Horde_Prefs_Cache_Base
{
    public const SESS_KEY = 'prefs_cache/';

    /**
     */
    public function get($scope)
    {
        $session = $this->_session();
        $key = self::SESS_KEY . $this->_params['user'] . '/' . $scope;

        return $session->hasScoped('horde', $key)
            ? $session->getScoped('horde', $key)
            : false;
    }

    /**
     * Invalidate the cached scope.
     *
     * {@see Horde_Prefs::store()} invokes this after a dirty scope has been
     * written to storage. Rather than re-serializing the scope into the
     * session here — which would race the lifecycle mirror at shutdown and
     * could be lost before reaching the session row — drop the slot so the
     * next request loads the scope fresh from storage and repopulates the
     * cache on a clean read path.
     */
    public function store($scope_ob)
    {
        $this->_session()->removeScoped(
            'horde',
            self::SESS_KEY . $this->_params['user'] . '/' . $scope_ob->scope
        );
    }

    /**
     */
    public function remove($scope = null)
    {
        $this->_session()->removeScoped(
            'horde',
            self::SESS_KEY . $this->_params['user'] . '/' . strval($scope)
        );
    }

    /**
     * Resolve the modern session-access slot lazily from the global
     * injector. The cache driver has no constructor of its own, so DI
     * happens at call time. Each call resolves fresh through the
     * accessor's passthrough, so post-regenerate values are visible.
     */
    private function _session(): SessionAccess
    {
        return $GLOBALS['injector']->getInstance(SessionAccess::class);
    }
}
