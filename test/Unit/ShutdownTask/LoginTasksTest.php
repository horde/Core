<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Test\Unit\ShutdownTask;

use Horde\Core\ShutdownTask\LoginTasks;
use Horde_LoginTasks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Horde_LoginTasks shutdown adapter — the timing-only bridge
 * that lets `horde/logintasks` persist via `Horde_Shutdown` instead of via
 * its own `register_shutdown_function`.
 */
#[CoversClass(LoginTasks::class)]
class LoginTasksTest extends TestCase
{
    #[Test]
    public function testShutdownDelegatesToWrappedInstance(): void
    {
        $loginTasks = $this->createMock(Horde_LoginTasks::class);
        $loginTasks->expects($this->once())
            ->method('shutdown');

        $adapter = new LoginTasks($loginTasks);
        $adapter->shutdown();
    }

    #[Test]
    public function testShutdownIsIdempotentAcrossMultipleCalls(): void
    {
        /* Horde_Shutdown::runTasks() should only call shutdown() once per
         * task, but the adapter shouldn't choke if its shutdown() is
         * invoked twice — defensive sanity check. */
        $loginTasks = $this->createMock(Horde_LoginTasks::class);
        $loginTasks->expects($this->exactly(2))
            ->method('shutdown');

        $adapter = new LoginTasks($loginTasks);
        $adapter->shutdown();
        $adapter->shutdown();
    }
}
