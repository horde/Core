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

namespace Horde\Core\Test\Unit\Factory;

use Horde\Core\Session\HordeSession;
use Horde_Core_Factory_ShareBase;
use Horde_Injector;
use Horde_Injector_TopLevel;
use Horde_Shutdown_Task;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Tests that Horde_Core_Factory_ShareBase persists pending share-list
 * caches via the modern HordeSession on shutdown, and that the factory
 * is wired as a Horde_Shutdown_Task so it rides through
 * Horde_Shutdown::runTasks() ahead of the pinned-final session shim
 * mirror. Without the Horde_Shutdown_Task interface and the matching
 * Horde_Shutdown::add() call in create(), the persist call would happen
 * via a raw register_shutdown_function and its setScoped writes would
 * land in HordeSession after the mirror had already snapshotted the
 * payload.
 */
#[CoversClass(Horde_Core_Factory_ShareBase::class)]
class ShareBaseFactoryTest extends TestCase
{
    #[Test]
    public function testFactoryImplementsHordeShutdownTask(): void
    {
        $injector = new Horde_Injector(new Horde_Injector_TopLevel());
        $factory = new Horde_Core_Factory_ShareBase($injector);

        $this->assertInstanceOf(Horde_Shutdown_Task::class, $factory);
    }

    #[Test]
    public function testShutdownPersistsPendingCachesViaHordeSession(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->expects($this->exactly(2))
            ->method('setScoped')
            ->willReturnCallback(function (string $app, string $key, mixed $value): void {
                /* Both pending entries must persist; capture-via-callback
                 * because PHPUnit 11 deprecates withConsecutive(). */
                $this->assertContains($app, ['kronolith', 'turba']);
                $this->assertStringStartsWith('horde_share/', $key);
            });

        $injector = new Horde_Injector(new Horde_Injector_TopLevel());
        $injector->setInstance(HordeSession::class, $session);
        $factory = new Horde_Core_Factory_ShareBase($injector);

        /* Directly populate the protected state shutdown() consumes. The
         * full create() path involves Horde_Registry, Horde_Perms,
         * Horde_Group and a configured share driver — all out of scope
         * for verifying the persistence wiring. */
        $kronolithShare = $this->makeShareWithListCache(['cal-1', 'cal-2']);
        $turbaShare = $this->makeShareWithListCache(['book-1']);

        $instancesProp = new ReflectionProperty($factory, '_instances');
        $instancesProp->setAccessible(true);
        $instancesProp->setValue($factory, [
            'kronolith_sql' => $kronolithShare,
            'turba_sql' => $turbaShare,
        ]);

        $toCacheProp = new ReflectionProperty($factory, '_toCache');
        $toCacheProp->setAccessible(true);
        $toCacheProp->setValue($factory, [
            'kronolith_sql' => ['kronolith', Horde_Core_Factory_ShareBase::STORAGE_KEY . 'sql'],
            'turba_sql' => ['turba', Horde_Core_Factory_ShareBase::STORAGE_KEY . 'sql'],
        ]);

        $factory->shutdown();
    }

    #[Test]
    public function testShutdownIsNoOpWhenNoPendingCaches(): void
    {
        $session = $this->createMock(HordeSession::class);
        $session->expects($this->never())->method('setScoped');

        $injector = new Horde_Injector(new Horde_Injector_TopLevel());
        $injector->setInstance(HordeSession::class, $session);
        $factory = new Horde_Core_Factory_ShareBase($injector);

        $factory->shutdown();
    }

    /**
     * Build a minimal stub that satisfies the only call shutdown() makes
     * on share instances: getListCache().
     *
     * @param array<int, string> $cache
     */
    private function makeShareWithListCache(array $cache): object
    {
        return new class ($cache) {
            /** @param array<int, string> $cache */
            public function __construct(private readonly array $cache) {}

            /** @return array<int, string> */
            public function getListCache(): array
            {
                return $this->cache;
            }
        };
    }
}
