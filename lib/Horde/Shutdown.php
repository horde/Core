<?php

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

/**
 * Global horde shutdown task queue.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 * @since     2.4.0
 */
class Horde_Shutdown
{
    /**
     * Tasks.
     *
     * @var array
     */
    private $_tasks = [];

    /**
     * Single-slot task pinned to run after every regular task.
     *
     * Used by the framework to ensure end-of-request work that depends on
     * all other shutdown tasks having finished (e.g. mirroring the modern
     * HordeSession payload back into $_SESSION before PHP's native session
     * save handler runs) executes last.
     *
     * Framework-internal. Not part of the public app-facing API; apps
     * should use {@see add()} / {@see addTask()}.
     *
     * @var Horde_Shutdown_Task|null
     */
    private $_finalTask = null;

    /**
     * Add a task to the global Horde shutdown queue.
     *
     * @param Horde_Shutdown_Task $task  Task to add.
     */
    public static function add(Horde_Shutdown_Task $task)
    {
        $GLOBALS['injector']->getInstance('Horde_Shutdown')->addTask($task);
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        register_shutdown_function([$this, 'runTasks']);
    }

    /**
     * Add a task to the shutdown queue.
     *
     * @param Horde_Shutdown_Task $task  Task to add.
     */
    public function addTask(Horde_Shutdown_Task $task)
    {
        $this->_tasks[get_class($task)] = $task;
    }

    /**
     * Pin a task to run after every regular shutdown task.
     *
     * Single-slot: last writer wins. Reserved for the framework's own
     * end-of-request work (currently the Horde_Session shim flushing the
     * modern HordeSession payload into $_SESSION). Apps should not call
     * this; use {@see addTask()} instead.
     *
     * @param Horde_Shutdown_Task $task  Task to run last.
     */
    public function addFinal(Horde_Shutdown_Task $task)
    {
        $this->_finalTask = $task;
    }

    /**
     * Run shutdown tasks.
     *
     * Runs every regular task in registration order, then the
     * single-slot final task (if any). Exceptions from individual tasks
     * are swallowed so one misbehaving task cannot block the others.
     */
    public function runTasks()
    {
        foreach ($this->_tasks as $val) {
            try {
                $val->shutdown();
            } catch (Exception $e) {
            }
        }

        if ($this->_finalTask !== null) {
            try {
                $this->_finalTask->shutdown();
            } catch (Exception $e) {
            }
        }
    }

}
