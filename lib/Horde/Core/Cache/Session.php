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
 * Cache data in session, offloading the data to the cache storage backend
 * when the data becomes too large.
 *
 * Data is stored via the modern PSR-4 {@see HordeSession} (scoped writes,
 * raw values). The wire format is therefore incompatible with values written
 * through the legacy `Horde_Session::set()` mask system, but this is a
 * cache: stale entries are simply treated as misses on the first read after
 * deploy and refilled from the cache backend.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2014-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 * @since     2.12.0
 */
class Horde_Core_Cache_Session extends Horde_Cache_Storage_Base
{
    /* Suffix to add to storage_key to produce stored key. */
    public const STORED_KEY = '_s';

    /**
     * The list of keys stored in the cache backend.
     *
     * @var array
     */
    protected $_stored = [];

    /**
     * The modern session.
     */
    protected HordeSession $_session;

    /**
     * @param array $params  Configuration parameters:
     *   - app: (string) Application to store session data under.
     *   - cache: (Horde_Cache) [REQUIRED] The backend cache driver used to
     *            store large entries.
     *   - maxsize: (integer) The maximum size of the data to store in the
     *              session (0 to always store in session).
     *   - session: (HordeSession) Modern session to read/write through. If
     *              omitted, resolved from the global injector for BC.
     *   - storage_key: (string) The storage key to save the session data
     *                  under.
     */
    public function __construct(array $params = [])
    {
        if (!isset($params['cache'])) {
            throw new InvalidArgumentException('Missing cache parameter.');
        }

        $this->_session = $params['session']
            ?? $GLOBALS['injector']->getInstance(HordeSession::class);
        unset($params['session']);

        parent::__construct(array_merge(
            [
                'app' => 'horde',
                'maxsize' => 5000,
                'storage_key' => 'sess_cache',
            ],
            $params
        ));
    }

    /**
     */
    protected function _initOb()
    {
        $stored = $this->_session->getScoped(
            $this->_params['app'],
            $this->_params['storage_key'] . self::STORED_KEY,
        );
        $this->_stored = is_array($stored) ? $stored : [];
    }

    /**
     * @param integer $lifetime  Ignored in this driver.
     */
    public function get($key, $lifetime = 0)
    {
        return isset($this->_stored[$key])
            ? $this->_params['cache']->get($this->_getCid($key, false), 0)
            : $this->_session->getScoped(
                $this->_params['app'],
                $this->_getCid($key, true)
            );
    }

    /**
     * @param integer $lifetime  Ignored in this driver.
     */
    public function set($key, $data, $lifetime = 0)
    {
        if ($this->_params['maxsize']
            && (strlen($data) > $this->_params['maxsize'])) {
            $this->_params['cache']->set($this->_getCid($key, false), $data);
            $this->_stored[$key] = 1;
            $this->_saveStored();
            $this->_session->removeScoped(
                $this->_params['app'],
                $this->_getCid($key, true)
            );
        } else {
            $this->_session->setScoped(
                $this->_params['app'],
                $this->_getCid($key, true),
                $data
            );
            if (isset($this->_stored[$key])) {
                unset($this->_stored[$key]);
                $this->_saveStored();
                $this->_params['cache']->expire($key);
            }
        }
    }

    /**
     * @param integer $lifetime  Ignored in this driver.
     */
    public function exists($key, $lifetime = 0)
    {
        return ($this->get($key) !== null && $this->get($key) !== false);
    }

    /**
     */
    public function expire($key)
    {
        $this->_session->removeScoped(
            $this->_params['app'],
            $this->_getCid($key, true)
        );
        if (isset($this->_stored[$key])) {
            unset($this->_stored[$key]);
            $this->_saveStored();
            $this->_params['cache']->expire($key);
        }
    }

    /**
     */
    public function clear()
    {
        $prefix = $this->_params['storage_key'] . '/';
        foreach ($this->_session->keysForApp($this->_params['app']) as $key) {
            if (str_starts_with($key, $prefix)) {
                $this->_session->removeScoped($this->_params['app'], $key);
            }
        }

        foreach (array_keys($this->_stored) as $key) {
            $this->_params['cache']->expire($key);
        }
        $this->_stored = [];
        $this->_saveStored();
    }

    /**
     */
    protected function _getCid($key, $in_session)
    {
        if ($in_session) {
            return $this->_params['storage_key'] . '/' . $key;
        }

        /* Stable per-session prefix. Derived from session_id() rather than a
         * CSRF token because such tokens are not stable across calls. The
         * cache-key role wants a stable session fingerprint, not a security
         * token. SHA-1 is good enough for uniqueness and avoids leaking the
         * raw session id into cache keys that may end up in backend logs. */
        return implode('|', [
            $this->_params['app'],
            sha1((string) session_id()),
            $key,
        ]);
    }

    /**
     * Save stored list to the session.
     */
    protected function _saveStored()
    {
        $this->_session->setScoped(
            $this->_params['app'],
            $this->_params['storage_key'] . self::STORED_KEY,
            $this->_stored
        );
    }

}
