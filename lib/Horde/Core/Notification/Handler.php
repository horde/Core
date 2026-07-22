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
use Horde\Core\Session\SessionAccess;

/**
 * Core Horde notification handler.
 *
 * Persists the per-session list of application notification handlers via
 * the modern PSR-4 {@see HordeSession} (scoped writes). The wire format
 * diverges from values previously written through the legacy
 * `Horde_Session::set()` path. Stale entries from the prior format are
 * treated as missing on the first read after deploy.
 *
 * Reads and writes go through {@see SessionAccess} so post-regenerate
 * values reach us on the next call — see horde/Core#190.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2014-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 * @since     2.18.0
 */
class Horde_Core_Notification_Handler extends Horde_Notification_Handler
{
    public const SESS_KEY = 'core_notification_handler';

    /**
     * The request-scoped session access slot.
     */
    protected SessionAccess $_session;

    /**
     * List of applications that contain notification handlers. Array with
     * keys as app names and values as boolean values indicating if the
     * handler has been loaded yet. False indicates attaching is not active.
     */
    protected $_apps = false;

    /**
     * @param Horde_Notification_Storage_Interface $storage  Notification storage.
     * @param SessionAccess|null                   $session  Modern session-access
     *                                                       slot for the per-session
     *                                                       app-handler cache.
     *                                                       If omitted, resolved
     *                                                       from the global
     *                                                       injector for BC.
     */
    public function __construct(
        Horde_Notification_Storage_Interface $storage,
        ?SessionAccess $session = null
    ) {
        parent::__construct($storage);
        $this->_session = $session
            ?? $GLOBALS['injector']->getInstance(SessionAccess::class);
    }

    /**
     */
    public function notify(array $options = [])
    {
        if ($this->_apps) {
            foreach ($this->_apps as $key => $val) {
                if (!$val) {
                    $this->addAppHandler($key);
                }
            }
            $this->_apps = false;
        }

        return parent::notify($options);
    }

    /**
     * Indicate that all application handlers are to be attached in this
     * access (if needed).
     */
    public function attachAllAppHandlers()
    {
        global $registry;

        /* Cache notification handler application method existence. */
        $this->_apps = $this->_session->getScoped('horde', self::SESS_KEY);
        if (!is_null($this->_apps)) {
            return;
        }

        $this->_apps = [];

        try {
            $apps = $registry->listApps(null, false, Horde_Perms::READ);
        } catch (Horde_Exception $e) {
            $apps = [];
        }

        foreach ($apps as $app) {
            if ($registry->hasFeature('notificationHandler', $app)) {
                $this->_apps[] = false;
            }
        }

        $this->_session->setScoped('horde', self::SESS_KEY, $this->_apps);
    }

    /**
     * Explicitly add an application's notification handlers (if they exist)
     * to the base handler.
     *
     * @param string $app  Application name.
     */
    public function addAppHandler($app)
    {
        global $registry;

        if (!$this->_apps || !empty($this->_apps[$app])) {
            return;
        }

        $this->_apps[$app] = true;

        try {
            $registry->callAppMethod(
                $app,
                'setupNotification',
                [
                    'args' => [$this],
                    'noperms' => true,
                ]
            );
        } catch (Exception $e) {
        }
    }

}
