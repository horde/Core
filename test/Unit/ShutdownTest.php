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

namespace Horde\Core\Test\Unit;

use Horde_Shutdown;
use Horde_Shutdown_Task;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A single shared sink the test fixtures append into.
 *
 * Horde_Shutdown::addTask() keys its queue by get_class($task), so the
 * fixtures below have to be distinct named classes — anonymous-class
 * recorders would all hash to the same key and overwrite each other.
 */
final class ShutdownTestRecord
{
    /** @var array<int, string> */
    public static array $log = [];

    public static function reset(): void
    {
        self::$log = [];
    }
}

abstract class ShutdownTestRecorder implements Horde_Shutdown_Task
{
    abstract protected function name(): string;

    public function shutdown(): void
    {
        ShutdownTestRecord::$log[] = $this->name();
    }
}

final class ShutdownTestRecorderA extends ShutdownTestRecorder
{
    protected function name(): string { return 'a'; }
}

final class ShutdownTestRecorderB extends ShutdownTestRecorder
{
    protected function name(): string { return 'b'; }
}

final class ShutdownTestRecorderC extends ShutdownTestRecorder
{
    protected function name(): string { return 'c'; }
}

final class ShutdownTestRecorderFinal extends ShutdownTestRecorder
{
    protected function name(): string { return 'final'; }
}

final class ShutdownTestRecorderFinalAlt extends ShutdownTestRecorder
{
    protected function name(): string { return 'replacement-final'; }
}

final class ShutdownTestThrower implements Horde_Shutdown_Task
{
    public function shutdown(): void
    {
        throw new \RuntimeException('boom');
    }
}

/**
 * Tests for Horde_Shutdown — in particular the addFinal() single-slot
 * pin used by Horde_Session to ensure its modern→$_SESSION mirror runs
 * after every other Horde_Shutdown_Task. The shim's mirror has to land
 * last because regular tasks may write through the shim into the modern
 * HordeSession after the shim itself has been registered.
 */
#[CoversClass(Horde_Shutdown::class)]
class ShutdownTest extends TestCase
{
    protected function setUp(): void
    {
        ShutdownTestRecord::reset();
    }

    /**
     * Build a Horde_Shutdown directly so we never touch the real
     * $GLOBALS['injector']. The static add() facade delegates to
     * addTask(), which is exercised here directly.
     */
    private function makeShutdown(): Horde_Shutdown
    {
        return new Horde_Shutdown();
    }

    #[Test]
    public function testRegularTasksRunInRegistrationOrder(): void
    {
        $shutdown = $this->makeShutdown();

        $shutdown->addTask(new ShutdownTestRecorderA());
        $shutdown->addTask(new ShutdownTestRecorderB());
        $shutdown->addTask(new ShutdownTestRecorderC());

        $shutdown->runTasks();

        $this->assertSame(['a', 'b', 'c'], ShutdownTestRecord::$log);
    }

    #[Test]
    public function testFinalTaskRunsAfterRegularTasks(): void
    {
        $shutdown = $this->makeShutdown();

        $shutdown->addTask(new ShutdownTestRecorderA());
        $shutdown->addTask(new ShutdownTestRecorderB());
        $shutdown->addFinal(new ShutdownTestRecorderFinal());

        $shutdown->runTasks();

        $this->assertSame(['a', 'b', 'final'], ShutdownTestRecord::$log);
    }

    /**
     * The original failure mode: the final task is registered BEFORE the
     * regular tasks. Without the pinned slot it would run first (insertion
     * order), and any session writes from the regular tasks would be lost.
     */
    #[Test]
    public function testFinalTaskRunsLastEvenWhenRegisteredFirst(): void
    {
        $shutdown = $this->makeShutdown();

        $shutdown->addFinal(new ShutdownTestRecorderFinal());
        $shutdown->addTask(new ShutdownTestRecorderA());
        $shutdown->addTask(new ShutdownTestRecorderB());

        $shutdown->runTasks();

        $this->assertSame(['a', 'b', 'final'], ShutdownTestRecord::$log);
    }

    #[Test]
    public function testAddFinalIsSingleSlotLastWriterWins(): void
    {
        $shutdown = $this->makeShutdown();

        $shutdown->addFinal(new ShutdownTestRecorderFinal());
        $shutdown->addFinal(new ShutdownTestRecorderFinalAlt());
        $shutdown->addTask(new ShutdownTestRecorderA());

        $shutdown->runTasks();

        $this->assertSame(['a', 'replacement-final'], ShutdownTestRecord::$log);
    }

    #[Test]
    public function testRunTasksWithoutFinalTaskRunsRegularTasksOnly(): void
    {
        $shutdown = $this->makeShutdown();

        $shutdown->addTask(new ShutdownTestRecorderA());

        $shutdown->runTasks();

        $this->assertSame(['a'], ShutdownTestRecord::$log);
    }

    #[Test]
    public function testFailingRegularTaskDoesNotBlockOthersOrFinalTask(): void
    {
        $shutdown = $this->makeShutdown();

        $shutdown->addTask(new ShutdownTestRecorderA());
        $shutdown->addTask(new ShutdownTestThrower());
        $shutdown->addTask(new ShutdownTestRecorderC());
        $shutdown->addFinal(new ShutdownTestRecorderFinal());

        $shutdown->runTasks();

        $this->assertSame(['a', 'c', 'final'], ShutdownTestRecord::$log);
    }

    #[Test]
    public function testFailingFinalTaskIsSwallowed(): void
    {
        $shutdown = $this->makeShutdown();

        $shutdown->addTask(new ShutdownTestRecorderA());
        $shutdown->addFinal(new ShutdownTestThrower());

        $shutdown->runTasks();

        $this->assertSame(['a'], ShutdownTestRecord::$log);
    }
}
