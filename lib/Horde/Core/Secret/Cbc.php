<?php

/**
 * Copyright 2015-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2015-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL
 * @package   Core
 */

use Horde\Core\Secret\SessionSecret;
use Horde\Core\Session\HordeSession;
use Horde\Crypt\Blowfish\Blowfish;

/**
 * Horde_Secret, using single session key, with CBC based Blowfish encryption.
 *
 * This is much more secure than the default Horde_Secret algorithm. It should
 * be used for all Horde_Secret/session encryption, but for BC purposes it
 * needs to live in a separate class for now.
 *
 * Uses the additional parameter 'iv' - the IV used to seed the CBC cipher.
 *
 * The per-session key persists primarily inside the modern
 * {@see HordeSession} payload (slot `_secret`/`key`) after
 * {@see setSession()} wires the session in. The legacy
 * `horde_secret_key` cookie remains as a backwards-compatible
 * fallback so a rollback to a pre-fix Core release can still read
 * pre-existing sessions, and so sessions written before the
 * payload-slot existed migrate transparently on first read.
 *
 * @todo  Merge this class with Horde_Core_Secret.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2015-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL
 * @package   Core
 * @since     2.20.0
 */
class Horde_Core_Secret_Cbc extends Horde_Core_Secret implements SessionSecret
{
    /** Slot scope where the per-session key lives in HordeSession. */
    private const KEY_SCOPE = '_secret';

    /** Slot name where the per-session key lives in HordeSession. */
    private const KEY_NAME = 'key';

    /**
     * Key used for current cached cipher object.
     *
     * @var string
     */
    protected $_cachedKey = '';

    /**
     * Modern session, wired by {@see setSession()}. Null before wiring
     * (e.g. during DI bootstrap, before HordeSessionFactory has built
     * the session) and during the legacy-only test path.
     *
     * @var ?HordeSession
     */
    protected ?HordeSession $_session = null;

    /**
     */
    protected function _getCipherOb($key)
    {
        $key = substr($key, 0, 56);

        if (!isset($this->_cipherCache[self::HORDE_KEYNAME])
            || $this->_cachedKey !== $key) {
            $this->_cipherCache[self::HORDE_KEYNAME] = Blowfish::cbc(
                $key,
                $this->_params['iv']
            );
            $this->_cachedKey = $key;
        }

        return $this->_cipherCache[self::HORDE_KEYNAME];
    }

    /**
     * Wire the modern session in. Called from
     * {@see \Horde\Core\Session\HordeSessionFactory::create()} right after
     * the session is built. Subsequent {@see getKey()} / {@see setKey()}
     * calls read and write the per-session key in the session payload
     * instead of relying exclusively on the cookie.
     */
    public function setSession(HordeSession $session): void
    {
        $this->_session = $session;
    }

    /**
     * Return the per-session encryption key.
     *
     * Resolution order:
     *   1. The session payload slot. Always wins when present — it is
     *      the location new code writes, and the location existing
     *      ciphertext is bound to once a session has gone through the
     *      modern write path even once.
     *   2. The legacy `horde_secret_key` cookie. Wins on first read of
     *      a session that pre-dates the payload slot; the value is
     *      copied INTO the session slot so subsequent reads land on
     *      the payload path.
     *   3. Falls through to {@see Horde_Core_Secret::getKey()} which
     *      mints a fresh random key (and writes the cookie). The new
     *      value is copied into the session slot too.
     */
    public function getKey($keyname = self::DEFAULT_KEY)
    {
        if ($this->_session !== null
            && $this->_session->hasScoped(self::KEY_SCOPE, self::KEY_NAME)) {
            $stored = $this->_session->getScoped(self::KEY_SCOPE, self::KEY_NAME);
            if (is_string($stored) && $stored !== '') {
                return $stored;
            }
        }

        if (isset($_COOKIE[self::HORDE_KEYNAME . '_key'])) {
            $cookie = (string) $_COOKIE[self::HORDE_KEYNAME . '_key'];
            $this->_session?->setScoped(self::KEY_SCOPE, self::KEY_NAME, $cookie);
            return $cookie;
        }

        // Neither slot nor cookie has a usable value. Mint a fresh
        // random key via setKey() (which writes both the cookie and
        // the session slot for us). parent::getKey() would not work
        // here: it falls through to session_id() — empty in CLI and
        // brittle even at runtime — when the session-name cookie is
        // absent, defeating the migration intent. setKey() is the
        // documented mint path.
        return $this->setKey($keyname);
    }

    /**
     * Set or rotate the per-session encryption key.
     *
     * Writes both the legacy cookie (via the parent) and the session
     * payload slot. Invalidates the cached cipher so the next
     * {@see _getCipherOb()} rebuilds it under the new key.
     */
    public function setKey($keyname = self::DEFAULT_KEY)
    {
        $key = parent::setKey($keyname);
        if (is_string($key) && $key !== '' && $this->_session !== null) {
            $this->_session->setScoped(self::KEY_SCOPE, self::KEY_NAME, $key);
        }
        // Force the cipher cache to rebuild against the new key on the
        // next encrypt/decrypt.
        $this->_cachedKey = '';
        return $key;
    }

    /**
     * Clear the per-session encryption key from both the cookie and the
     * session payload.
     */
    public function clearKey($keyname = self::DEFAULT_KEY)
    {
        $result = parent::clearKey($keyname);
        $this->_session?->removeScoped(self::KEY_SCOPE, self::KEY_NAME);
        $this->_cachedKey = '';
        return $result;
    }
}

