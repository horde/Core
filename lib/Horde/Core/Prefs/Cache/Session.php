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

/**
 * Cache storage implementation using HordeSession.
 *
 * Reads and writes go through the modern PSR-4 {@see HordeSession}. The wire
 * format diverges from values previously written through the legacy
 * `Horde_Session::set()` path. Stale entries from the prior format are
 * treated as missing on the first read after deploy.
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
     */
    public function store($scope_ob)
    {
        $this->_session()->setScoped(
            'horde',
            self::SESS_KEY . $this->_params['user'] . '/' . $scope_ob->scope,
            $scope_ob
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
     * Resolve the modern session lazily from the global injector. The cache
     * driver has no constructor of its own, so DI happens at call time.
     */
    private function _session(): HordeSession
    {
        return $GLOBALS['injector']->getInstance(HordeSession::class);
    }
}
