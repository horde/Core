<?php

/**
 * A Horde_Injector based Horde_Share factory.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 */

use Horde\Core\Session\HordeSession;

class Horde_Core_Factory_ShareBase extends Horde_Core_Factory_Base
{
    /** Session storage key. */
    public const STORAGE_KEY = 'horde_share/';

    /**
     * Local cache of created share instances.
     *
     * @var array
     */
    protected $_instances = [];

    /**
     * Cache of share entries.
     *
     * @var array
     */
    protected $_toCache = [];

    /**
     * Returns the share driver instance.
     *
     * @param string $app     The application scope of the share. If empty,
     *                        default to current application.
     * @param string $driver  The storage driver to use. If empty, use the
     *                        globally configured storage driver.
     *
     * @return Horde_Share_Base  The share driver instance.
     */
    public function create($app = null, $driver = null)
    {
        global $conf;
        $registry = $this->_injector->getInstance('Horde_Registry');

        if (empty($driver)) {
            $driver = $conf['share']['driver'];
        }
        if (empty($app)) {
            $app = $registry->getApp();
        }

        $sig = $app . '_' . $driver;
        if (isset($this->_instances[$sig])) {
            return $this->_instances[$sig];
        }

        $class = $this->_getDriverName($driver, 'Horde_Share');
        $ob = new $class($app, $registry->getAuth(), $this->_injector->getInstance('Horde_Perms'), $this->_injector->getInstance('Horde_Group'));
        $cb = new Horde_Core_Share_FactoryCallback($app, $driver);
        $ob->setShareCallback([$cb, 'create']);
        $ob->setLogger($this->_injector->getInstance('Horde_Log_Logger'));

        if (!empty($conf['share']['cache'])) {
            $session = $this->_injector->getInstance(HordeSession::class);
            $cache_sig = self::STORAGE_KEY . $driver;
            $listCache = $session->getScoped($app, $cache_sig);
            $ob->setListCache($listCache);

            if (empty($this->_toCache)) {
                register_shutdown_function([$this, 'shutdown']);
            }

            $this->_toCache[$sig] = [$app, $cache_sig];
        }

        $this->_instances[$sig] = $ob;

        return $ob;
    }

    /**
     * Shutdown function.
     */
    public function shutdown()
    {
        $session = $this->_injector->getInstance(HordeSession::class);

        foreach ($this->_toCache as $sig => $val) {
            try {
                $session->setScoped($val[0], $val[1], $this->_instances[$sig]->getListCache());
            } catch (Horde_Exception $e) {
            }
        }
    }

}
