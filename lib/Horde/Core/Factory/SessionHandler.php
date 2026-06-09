<?php

use Horde\Injector\Injector;

/**
 * Factory for creating Horde_SessionHandler objects.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_SessionHandler extends Horde_Core_Factory_Injector
{
    /**
     * Storage driver.
     *
     * @since 2.5.0
     *
     * @var Horde_SessionHandler_Storage
     */
    public $storage;

    /**
     * Attempts to return a concrete instance based on $driver.
     *
     * @return Horde_SessionHandler_Driver  The newly created concrete
     *                                      instance.
     * @throws Horde_SessionHandler_Exception
     */
    public function create(Horde_Injector|Injector $injector)
    {
        global $conf;

        if (empty($conf['sessionhandler']['type'])) {
            $driver = 'Builtin';
        } else {
            $driver = $conf['sessionhandler']['type'];
            if (!strcasecmp($driver, 'None')) {
                $driver = 'Builtin';
            }
        }
        $params = Horde::getDriverConfig('sessionhandler', $driver);

        $driver = basename(Horde_String::lower($driver));

        switch ($driver) {
            case 'builtin':
                break;

            case 'hashtable':
                // DEPRECATED
            case 'memcache':
                $params['hashtable'] = $injector->getInstance('Horde_HashTable');
                $driver = 'hashtable';
                break;

            case 'nosql':
                $nosql = $injector->getInstance('Horde_Core_Factory_Nosql')->create('horde', 'sessionhandler');
                if ($nosql instanceof Horde_Mongo_Client) {
                    $params['mongo_db'] = $nosql;
                    $driver = 'Horde_SessionHandler_Storage_Mongo';
                }
                break;

            case 'sql':
                $factory = $injector->getInstance('Horde_Core_Factory_Db');
                $config = $factory->getConfig('sessionhandler');
                unset($config['umask'], $config['driverconfig']);
                $params['db'] = $factory->createDb($config);
                break;
        }

        $class = $this->_getDriverName($driver, 'Horde_SessionHandler_Storage');
        $storage = $this->storage = new $class($params);

        if ((!empty($conf['sessionhandler']['hashtable'])
             || !empty($conf['sessionhandler']['memcache']))
            && !in_array($driver, ['builtin', 'hashtable'])) {
            $storage = new Horde_SessionHandler_Storage_Stack([
                'stack' => [
                    new Horde_SessionHandler_Storage_Hashtable([
                        'hashtable' => $injector->getInstance('Horde_HashTable'),
                    ]),
                    $this->storage,
                ],
            ]);
        }

        /* Always pass noset=true: the modern Horde\SessionHandler\SessionHandler
         * is the wire-bound save handler (installed by Horde_Session::setup()).
         * Letting the legacy handler call session_set_save_handler() would
         * overwrite that registration. This factory now exists only to build
         * a working storage-backed Horde_SessionHandler for the CLI tools
         * (bin/horde-sessions-gc, bin/horde-active-sessions); they will be
         * migrated to Horde\SessionHandler\SessionAdministrator in a follow-up
         * PR after which this factory can be removed entirely. */
        return new Horde_SessionHandler(
            $storage,
            [
                'logger' => $injector->getInstance('Horde_Log_Logger'),
                'no_md5' => true,
                'noset' => true,
                'parse' => [$this, 'readSessionData'],
            ]
        );
    }

    /**
     * Reads session data to determine if it contains Horde authentication
     * credentials.
     *
     * @param string $session_data  The session data.
     *
     * @return array  An array of the user's sesion information if
     *                authenticated or false.  The following information is
     *                returned: userid, timestamp, remoteAddr, browser, apps.
     */
    public function readSessionData($session_data)
    {
        if (empty($session_data)) {
            return false;
        }

        /* Need to do some session magic.  Store old session, clear it out,
         * and use PHP's session_decode() to decode the incoming data.  Then
         * search for the needed auth entries and swap the old session data
         * back. */
        $old_sess = $_SESSION;
        $_SESSION = [];

        if (session_id()) {
            $new_sess = false;
            session_decode($session_data);
        } else {
            $stub = new Horde_Support_Stub();

            session_set_save_handler(
                [$this, '_returnTrue'],
                [$this, '_returnTrue'],
                [$stub, 'read'],
                [$stub, 'write'],
                [$this, '_returnTrue'],
                [$this, '_returnTrue']
            );

            ob_start();
            session_start();
            ob_end_clean();

            $new_sess = true;
            session_decode($session_data);
            $GLOBALS['session']->session_data = $_SESSION;
        }

        $data = $GLOBALS['registry']->getAuthInfo();
        $apps = $GLOBALS['registry']->getAuthApps();

        if ($new_sess) {
            session_destroy();
            $GLOBALS['session']->session_data = $old_sess;
        }

        $_SESSION = $old_sess;

        return isset($data['userId'])
            ? [
                'apps' => $apps,
                'browser' => $data['browser'],
                'remoteAddr' => $data['remoteAddr'],
                'timestamp' => $data['timestamp'],
                'userid' => $data['userId'],
            ]
            : false;
    }

    /**
     * Stub method.
     *
     * @return boolean True
     */
    protected function _returnTrue()
    {
        return true;
    }
}
