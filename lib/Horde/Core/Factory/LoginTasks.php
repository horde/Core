<?php

/**
 * A Horde_Injector:: based Horde_LoginTasks:: factory.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

use Horde\Core\ShutdownTask\LoginTasks as LoginTasksShutdownTask;

/**
 * A Horde_Injector:: based Horde_LoginTasks:: factory.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_Core_Factory_LoginTasks extends Horde_Core_Factory_Base
{
    /**
     * Instances.
     *
     * @var array
     */
    private $_instances = [];

    /**
     * Return the Horde_LoginTasks:: instance.
     *
     * @param string $app  The current application.
     *
     * @return Horde_Core_LoginTasks|boolean  The singleton instance. Returns
     *                                        false if logintasks not
     *                                        available.
     */
    public function create($app)
    {
        if (!$GLOBALS['registry']->getAuth()) {
            return false;
        }

        if (!isset($this->_instances[$app])) {
            $instance = new Horde_Core_LoginTasks(
                new Horde_Core_LoginTasks_Backend_Horde($app),
                $app
            );
            $this->_instances[$app] = $instance;

            /* Wire shutdown-time persistence through Horde_Shutdown so the
             * persist call lands inside Horde_Shutdown::runTasks() and is
             * therefore picked up by the pinned-final session shim mirror.
             * The library used to register its own
             * register_shutdown_function callback, but that fires *after*
             * Horde_Shutdown::runTasks() and dropped any session writes the
             * persist call made — including the `tasklist = true` "tasks
             * done for this session" marker, causing per-login tasks like
             * LastLogin to re-fire on every request. */
            Horde_Shutdown::add(new LoginTasksShutdownTask($instance));
        }

        return $this->_instances[$app];
    }

}
