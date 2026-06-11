<?php

/**
 * This class extends the base LoginTasks class in order to ensure Horde
 * tasks are always run first.
 *
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

use Horde\Core\Session\HordeSession;

class Horde_Core_LoginTasks extends Horde_LoginTasks
{
    /**
     * Horde application to run login tasks for.
     *
     * @var string
     */
    protected $_app;

    /**
     * @param string $app  Horde application string.
     */
    public function __construct(Horde_LoginTasks_Backend $backend, $app)
    {
        $this->_app = $app;

        /* Pass false: Core owns the shutdown-time persist call via
         * Horde\Core\ShutdownTask\LoginTasks, which is registered through
         * Horde_Shutdown by the factory. The library's own
         * register_shutdown_function callback would fire *after*
         * Horde_Shutdown::runTasks() (which includes the pinned-final
         * session shim mirror) and any session writes from it would be
         * dropped. Requires horde/logintasks >= 3.x with the
         * $registerShutdown constructor flag. */
        parent::__construct($backend, false);
    }

    /**
     */
    public function runTasks(array $opts = [])
    {
        if (!isset($opts['url'])) {
            $opts['url'] = Horde::selfUrl(true, true, true);
        }

        if (($this->_app != 'horde')
            && ($GLOBALS['injector']->getInstance(HordeSession::class)
                ->getScoped('horde', 'logintasks') !== true)) {
            $GLOBALS['injector']->getInstance('Horde_Core_Factory_LoginTasks')->create('horde')->runTasks($opts);
        }

        parent::runTasks($opts);
    }

}
