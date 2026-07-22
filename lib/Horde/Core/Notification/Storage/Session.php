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
 * A class that stores notifications in the session.
 *
 * Reads and writes go through the modern PSR-4 {@see HordeSession} via
 * a request-scoped {@see SessionAccess} slot, so post-regenerate values
 * are visible on the next call (horde/Core#190). The wire format diverges
 * from values written through legacy `Horde_Session::set()` with
 * TYPE_ARRAY/TYPE_OBJECT masks, but notifications are display-only
 * transient state: stale entries from the prior shim format are simply
 * treated as missing on the first read after deploy.
 *
 * The "session active" guard is preserved as a like-for-like translation of
 * the legacy `Horde_Session::isActive()` flag, mapped onto PHP's native
 * `session_status()`. Notifications pushed before PHP has an active session
 * are queued in `$_cached` and replayed once the session opens.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2010-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class Horde_Core_Notification_Storage_Session implements Horde_Notification_Storage_Interface
{
    /**
     * The request-scoped session access slot.
     */
    protected SessionAccess $_session;

    /**
     * Cached notifications if session is not active.
     *
     * @var array
     */
    protected $_cached = [];

    /**
     * @param SessionAccess|null $session  Modern session-access slot to
     *                                     read/write through. If omitted,
     *                                     resolved from the global injector
     *                                     for BC.
     */
    public function __construct(?SessionAccess $session = null)
    {
        $this->_session = $session
            ?? $GLOBALS['injector']->getInstance(SessionAccess::class);
    }

    /**
     */
    public function get($key)
    {
        $this->_processCached();
        return $this->_session->getScoped('horde', 'notify/' . $key);
    }

    /**
     */
    public function set($key, $value)
    {
        if ($this->_isSessionActive()) {
            $this->_processCached();
            if (!empty($value) || $this->exists($key)) {
                $this->_session->setScoped('horde', 'notify/' . $key, $value);
            }
        } else {
            $this->_cached[] = [$key, $value];
        }
    }

    /**
     */
    public function exists($key)
    {
        $this->_processCached();
        return $this->_session->hasScoped('horde', 'notify/' . $key);
    }

    /**
     */
    public function clear($key)
    {
        $this->_cached = [];
        $this->_session->removeScoped('horde', 'notify/' . $key);
    }

    /**
     */
    public function push($listener, Horde_Notification_Event $event)
    {
        if ($this->_isSessionActive()) {
            $events = $this->_session->getScoped('horde', 'notify/' . $listener);
            if (!is_array($events)) {
                $events = [];
            }
            $events[] = $event;
            $this->_session->setScoped('horde', 'notify/' . $listener, $events);
        } else {
            $this->_cached[] = [$listener, $event];
        }
    }

    /**
     */
    protected function _processCached()
    {
        if (!empty($this->_cached) && $this->_isSessionActive()) {
            $cached = $this->_cached;
            $this->_cached = [];

            foreach ($cached as $val) {
                if ($val[1] instanceof Horde_Notification_Event) {
                    $this->push($val[0], $val[1]);
                } else {
                    $this->set($val[0], $val[1]);
                }
            }
        }
    }

    /**
     * Whether PHP's session is currently active. Replaces the legacy
     * `Horde_Session::isActive()` shim flag.
     */
    protected function _isSessionActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
