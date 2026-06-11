<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\ShutdownTask;

use Horde_LoginTasks;
use Horde_Shutdown_Task;

/**
 * Shutdown task that persists a Horde_LoginTasks instance at end of request.
 *
 * Owns the request-end timing for `horde/logintasks` so the library itself
 * does not have to. The library's legacy class still self-installs a PHP
 * shutdown handler when its `$registerShutdown` constructor flag is left at
 * its default of true; Core builds it with that flag set to false and wires
 * this adapter through `Horde_Shutdown` instead. This guarantees the
 * persist call lands inside `Horde_Shutdown::runTasks()` — i.e. before the
 * session shim's pinned-final mirror copies the modern `HordeSession`
 * payload back into `$_SESSION`. Any session writes the persist call makes
 * therefore reach storage. Without this adapter the library's
 * register_shutdown_function callback fires *after*
 * `Horde_Shutdown::runTasks()` and the writes are silently dropped.
 *
 * The adapter is thin on purpose. The library knows what to persist; this
 * class only owns the *when*. New `horde/core` code that consumes the
 * modern PSR-4 `Horde\LoginTasks\LoginTasks` class should instead call the
 * library's `persist()` method directly at the appropriate moment, or
 * compose its own adapter — this class targets the legacy
 * `Horde_LoginTasks` shape that Core's existing factory builds.
 */
class LoginTasks implements Horde_Shutdown_Task
{
    public function __construct(
        private readonly Horde_LoginTasks $loginTasks,
    ) {}

    public function shutdown(): void
    {
        $this->loginTasks->shutdown();
    }
}
