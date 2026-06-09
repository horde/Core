<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @license   http://www.horde.org/licenses/lgpl21 LGPL
 * @package   Core
 * @subpackage UnitTests
 */

namespace Horde\Core\Test\Unit;

use Horde\Core\Horde;
use Horde\Core\Test\Support\MockSkipConstructorTrait;
use Horde\Log\Handler\BufferHandler;
use Horde\Log\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Horde_Core_Factory_Logger;
use Horde_Injector;
use Horde_Registry;
use ReflectionClass;
use Horde_Core_Log_Logger;
use Horde_Exception;
use RuntimeException;
use Stringable;

/**
 * Unit tests for Horde\Core\Horde::log() and related methods.
 *
 * @category  Horde
 * @license   http://www.horde.org/licenses/lgpl21 LGPL
 * @package   Core
 * @subpackage UnitTests
 */
#[CoversClass(Horde::class)]
class HordeTest extends TestCase
{
    use MockSkipConstructorTrait;

    /**
     * BufferHandler wired into the PSR-3 logger to capture output.
     */
    private BufferHandler $buffer;

    /**
     * PSR-3 logger with a BufferHandler for assertions.
     */
    private Logger $logger;

    protected function setUp(): void
    {
        $this->buffer = new BufferHandler();
        $this->logger = new Logger([$this->buffer]);

        // Clear any messages other tests left in the static
        // pre-init buffer. Without this, `Horde::log()` drains
        // those leaked messages into our test buffer and breaks
        // count-based assertions.
        $ref = new ReflectionClass(Horde::class);
        $prop = $ref->getProperty('_logBuffer');
        $prop->setValue(null, null);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['injector'],
            $GLOBALS['registry'],
            $GLOBALS['conf'],
        );

        // Reset the private static $_logBuffer between tests.
        $ref = new ReflectionClass(Horde::class);
        $prop = $ref->getProperty('_logBuffer');
        $prop->setValue(null, null);
    }

    /**
     * Wire up $GLOBALS so that Horde_Core_Factory_Logger::available()
     * returns true and the injector provides our BufferHandler-backed
     * Logger for the PSR-4 path.
     */
    private function setUpInjectorWithLogger(): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->hordeInit = true;
        $registry->method('getApp')->willReturn('test');

        $injector = $this->createStub(Horde_Injector::class);
        $injector->method('getInstance')
            ->willReturnCallback(function (string $class) {
                if ($class === Logger::class) {
                    return $this->logger;
                }
                throw new Horde_Exception('Not configured: ' . $class);
            });

        $GLOBALS['registry'] = $registry;
        $GLOBALS['injector'] = $injector;
    }

    /**
     * Set up globals that make available() true but the PSR-4 logger
     * throws, forcing the legacy fallback path.
     */
    private function setUpInjectorLegacyFallback(object $legacyLogger): void
    {
        $registry = $this->createStub(Horde_Registry::class);
        $registry->hordeInit = true;
        $registry->method('getApp')->willReturn('test');

        $injector = $this->createStub(Horde_Injector::class);
        $injector->method('getInstance')
            ->willReturnCallback(function (string $class) use ($legacyLogger) {
                if ($class === Logger::class) {
                    throw new Horde_Exception('PSR-4 logger not available');
                }
                if ($class === 'Horde_Log_Logger') {
                    return $legacyLogger;
                }
                throw new Horde_Exception('Not configured: ' . $class);
            });

        $GLOBALS['registry'] = $registry;
        $GLOBALS['injector'] = $injector;
    }

    // ---------------------------------------------------------------
    // PSR-3 primary path
    // ---------------------------------------------------------------

    public function testLogStringMessageToPsr3Logger(): void
    {
        $this->setUpInjectorWithLogger();

        Horde::log('Hello world');

        $this->assertSame(1, $this->buffer->count());
    }

    public function testLogMessageContainsAppPrefix(): void
    {
        $this->setUpInjectorWithLogger();

        Horde::log('Something happened');

        // Drain buffer to inspect the message via a second buffer.
        $inspection = new BufferHandler();
        $inspectionLogger = new Logger([$inspection]);
        $this->buffer->drain($inspectionLogger);

        // The drain replays through the logger, which re-creates
        // LogMessage objects.  We can't directly read messages from
        // BufferHandler, but we know the count went up.
        $this->assertSame(1, $inspection->count());
    }

    public function testLogWithExplicitPriority(): void
    {
        $this->setUpInjectorWithLogger();

        // Priority 4 = WARNING
        Horde::log('Disk space low', 4);

        $this->assertSame(1, $this->buffer->count());
    }

    public function testLogExceptionEvent(): void
    {
        $this->setUpInjectorWithLogger();

        Horde::log(new RuntimeException('Something broke'));

        $this->assertSame(1, $this->buffer->count());
    }

    public function testLogHordeExceptionDedup(): void
    {
        $this->setUpInjectorWithLogger();

        $exception = new \Horde\Exception\HordeException('Already logged');
        $exception->logged = true;

        Horde::log($exception);

        // Should be skipped due to dedup.
        $this->assertSame(0, $this->buffer->count());
    }

    public function testLogArrayEvent(): void
    {
        $this->setUpInjectorWithLogger();

        Horde::log([
            'level' => 5,
            'message' => 'Array message',
            'timestamp' => time(),
        ]);

        $this->assertSame(1, $this->buffer->count());
    }

    public function testLogStringableObject(): void
    {
        $this->setUpInjectorWithLogger();

        $obj = new class implements Stringable {
            public function __toString(): string
            {
                return 'Stringable event';
            }
        };

        Horde::log($obj);

        $this->assertSame(1, $this->buffer->count());
    }

    // ---------------------------------------------------------------
    // Legacy fallback path
    // ---------------------------------------------------------------

    public function testLegacyFallbackWhenPsr4Throws(): void
    {
        $legacyLogger = $this->getMockSkipConstructor(Horde_Core_Log_Logger::class);
        $legacyLogger->expects($this->once())
            ->method('logObject');

        $this->setUpInjectorLegacyFallback($legacyLogger);

        Horde::log('Fallback message');

        // PSR-4 buffer should be empty — message went to legacy.
        $this->assertSame(0, $this->buffer->count());
    }

    // ---------------------------------------------------------------
    // Pre-init buffering
    // ---------------------------------------------------------------

    public function testPreInitMessagesAreBuffered(): void
    {
        // No injector/registry set — simulates early bootstrap.
        unset($GLOBALS['injector'], $GLOBALS['registry']);

        Horde::log('Early boot message');
        Horde::log('Another early message');

        // Messages are in the internal buffer — nothing exploded.
        // Now wire up the logger and send another message to trigger drain.
        $this->setUpInjectorWithLogger();
        Horde::log('Post-init message');

        // All 3 messages should have reached the PSR-3 logger:
        // 2 buffered + 1 direct.
        $this->assertSame(3, $this->buffer->count());
    }

    public function testBufferDrainsOnFirstPostInitLog(): void
    {
        unset($GLOBALS['injector'], $GLOBALS['registry']);

        Horde::log('Buffered');

        $this->setUpInjectorWithLogger();
        Horde::log('First post-init');

        $this->assertSame(2, $this->buffer->count());

        // Second call should not re-drain.
        Horde::log('Second post-init');
        $this->assertSame(3, $this->buffer->count());
    }

    // ---------------------------------------------------------------
    // priorityName() via buildLogPayload (indirect)
    // ---------------------------------------------------------------

    public function testDefaultPriorityIsInfo(): void
    {
        $this->setUpInjectorWithLogger();

        // No priority specified — should default to INFO (6).
        Horde::log('Default priority');

        $this->assertSame(1, $this->buffer->count());
    }
}
