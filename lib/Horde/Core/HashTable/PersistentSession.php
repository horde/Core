<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2014-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

use Horde\Core\Session\HordeSession;

/**
 * Hashtable implementation that ensures persistency of data within a given
 * session, without using session storage. Instead, VFS is used to store the
 * data.
 *
 * The data is attempted to be removed at the end of the session, so there is
 * no guarantee of persistence across sessions.
 *
 * NOTE: This is a session-scoped temp file manager, not a caching or
 * data-structure use case. It abuses the HashTable_Vfs class hierarchy to get
 * key-based get/set/delete semantics over VFS file storage with automatic
 * session-bound cleanup. The modern Horde\HashTable\ interfaces (PSR-4 in
 * src/) do not cover this pattern. When modernizing, replace with a dedicated
 * TempFileStore service that wraps VFS directly.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2014-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 * @since     2.13.0
 */
class Horde_Core_HashTable_PersistentSession extends Horde_Core_HashTable_Vfs implements Horde_Registry_Logout_Task
{
    /** Session data storage key. */
    public const SESS_KEY = 'psession_keys';

    /** The virtual path to use for VFS data (temporary storage). */
    public const VFS_PATH = '.horde/core/psession_data';

    /**
     * The modern session.
     */
    protected HordeSession $_session;

    /**
     * @param array $params  Configuration parameters:
     *   - session: (HordeSession) Modern session for the key list. If
     *              omitted, resolved from the global injector for BC.
     */
    public function __construct(array $params = [])
    {
        $this->_session = $params['session']
            ?? $GLOBALS['injector']->getInstance(HordeSession::class);

        /* Stable per-session VFS prefix. Derived from session_id() rather
         * than a CSRF token because such tokens are not stable across calls.
         * The path-prefix role wants a stable session fingerprint, not a
         * security token. SHA-1 is good enough for uniqueness and keeps the
         * raw session id out of VFS paths. */
        parent::__construct([
            'prefix' => sha1((string) session_id()),
            'vfspath' => self::VFS_PATH,
        ]);

        $this->gc(86400);
    }

    /**
     */
    public function set($key, $val, array $opts = [])
    {
        if (!parent::set($key, $val, $opts)) {
            return false;
        }

        $data_keys = $this->_getKeys();

        if (empty($data_keys)) {
            $logout = new Horde_Registry_Logout();
            $logout->add($this);
        }

        $data_keys[] = $key;

        $this->_session->setScoped('horde', self::SESS_KEY, $data_keys);

        return true;
    }

    /* Horde_Registry_Logout_Task method. */

    /**
     */
    public function logoutTask()
    {
        /* Rather than relying on GC, try to clean all data remnants in
         * the same session they were created. */
        $this->delete($this->_getKeys());
    }

    /* Internal methods. */

    /**
     * Return the list of keys that have been saved to VFS this session.
     *
     * @return array  List of keys.
     */
    protected function _getKeys()
    {
        $keys = $this->_session->getScoped('horde', self::SESS_KEY);

        return is_array($keys) ? $keys : [];
    }

}
