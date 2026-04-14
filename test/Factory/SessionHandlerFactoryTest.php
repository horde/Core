<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Factory;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\Core\Factory\SessionHandlerFactory;
use Horde\Core\Service\HordeDbService;
use Horde\SessionHandler\SessionHandler;
use Horde\SessionHandler\Storage\BuiltinBackend;
use Horde\SessionHandler\Storage\FileBackend;
use Horde\SessionHandler\Storage\HashtableBackend;
use Horde\SessionHandler\Storage\SqlBackend;
use Horde\SessionHandler\Storage\StackBackend;
use Horde\Db\Adapter;
use Horde_HashTable_Memory;
use Horde_Injector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

#[CoversClass(SessionHandlerFactory::class)]
class SessionHandlerFactoryTest extends TestCase
{
    private SessionHandlerFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SessionHandlerFactory();
    }

    /**
     * Extract the private 'backend' property from a SessionHandler via reflection.
     */
    private function getBackend(SessionHandler $handler): object
    {
        $prop = new ReflectionProperty(SessionHandler::class, 'backend');

        return $prop->getValue($handler);
    }

    private function createInjector(array $conf, bool $withDb = false, bool $withHashTable = false): Horde_Injector
    {
        $state = new State($conf);

        $loader = $this->createMock(ConfigLoader::class);
        $loader->method('load')
            ->with('horde')
            ->willReturn($state);

        $injector = $this->createMock(Horde_Injector::class);

        $map = [
            [ConfigLoader::class, $loader],
        ];

        if ($withDb) {
            $adapter = $this->createMock(Adapter::class);
            $dbService = $this->createMock(HordeDbService::class);
            $dbService->method('getAdapter')->willReturn($adapter);
            $map[] = [HordeDbService::class, $dbService];
        }

        if ($withHashTable) {
            $ht = $this->createMock(Horde_HashTable_Memory::class);
            $map[] = ['Horde_HashTable', $ht];
        }

        $injector->method('getInstance')
            ->willReturnMap($map);

        return $injector;
    }

    #[Test]
    public function testDefaultDriverIsBuiltin(): void
    {
        $injector = $this->createInjector([]);

        $handler = $this->factory->create($injector);

        self::assertInstanceOf(SessionHandler::class, $handler);
        self::assertInstanceOf(BuiltinBackend::class, $this->getBackend($handler));
    }

    #[Test]
    public function testNoneDriverMapsToBuiltin(): void
    {
        $injector = $this->createInjector([
            'sessionhandler' => ['type' => 'None'],
        ]);

        $handler = $this->factory->create($injector);

        self::assertInstanceOf(BuiltinBackend::class, $this->getBackend($handler));
    }

    #[Test]
    public function testSqlDriver(): void
    {
        $injector = $this->createInjector(
            ['sessionhandler' => ['type' => 'sql']],
            withDb: true,
        );

        $handler = $this->factory->create($injector);

        self::assertInstanceOf(SqlBackend::class, $this->getBackend($handler));
    }

    #[Test]
    public function testSqlDriverWithCustomTable(): void
    {
        $injector = $this->createInjector(
            ['sessionhandler' => ['type' => 'sql', 'params' => ['table' => 'custom_sessions']]],
            withDb: true,
        );

        $handler = $this->factory->create($injector);

        $backend = $this->getBackend($handler);
        self::assertInstanceOf(SqlBackend::class, $backend);

        // Verify the table name via reflection
        $tableProp = new ReflectionProperty(SqlBackend::class, 'table');
        self::assertSame('custom_sessions', $tableProp->getValue($backend));
    }

    #[Test]
    public function testHashtableDriver(): void
    {
        $injector = $this->createInjector(
            ['sessionhandler' => ['type' => 'hashtable']],
            withHashTable: true,
        );

        $handler = $this->factory->create($injector);

        self::assertInstanceOf(HashtableBackend::class, $this->getBackend($handler));
    }

    #[Test]
    public function testMemcacheDriverMapsToHashtable(): void
    {
        $injector = $this->createInjector(
            ['sessionhandler' => ['type' => 'memcache']],
            withHashTable: true,
        );

        $handler = $this->factory->create($injector);

        self::assertInstanceOf(HashtableBackend::class, $this->getBackend($handler));
    }

    #[Test]
    public function testFileDriver(): void
    {
        $tempDir = sys_get_temp_dir();
        $injector = $this->createInjector([
            'sessionhandler' => ['type' => 'file', 'params' => ['path' => $tempDir]],
        ]);

        $handler = $this->factory->create($injector);

        self::assertInstanceOf(FileBackend::class, $this->getBackend($handler));
    }

    #[Test]
    public function testUnknownDriverThrows(): void
    {
        $injector = $this->createInjector([
            'sessionhandler' => ['type' => 'redis_cluster'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported session driver: redis_cluster');

        $this->factory->create($injector);
    }

    #[Test]
    public function testSqlWithHashtableCacheCreatesStack(): void
    {
        $injector = $this->createInjector(
            [
                'sessionhandler' => [
                    'type' => 'sql',
                    'hashtable' => true,
                ],
            ],
            withDb: true,
            withHashTable: true,
        );

        $handler = $this->factory->create($injector);

        self::assertInstanceOf(StackBackend::class, $this->getBackend($handler));
    }

    #[Test]
    public function testSqlWithMemcacheCacheCreatesStack(): void
    {
        $injector = $this->createInjector(
            [
                'sessionhandler' => [
                    'type' => 'sql',
                    'memcache' => true,
                ],
            ],
            withDb: true,
            withHashTable: true,
        );

        $handler = $this->factory->create($injector);

        self::assertInstanceOf(StackBackend::class, $this->getBackend($handler));
    }

    #[Test]
    public function testHashtableDriverDoesNotStack(): void
    {
        $injector = $this->createInjector(
            [
                'sessionhandler' => [
                    'type' => 'hashtable',
                    'hashtable' => true,
                ],
            ],
            withHashTable: true,
        );

        $handler = $this->factory->create($injector);

        // Should NOT be wrapped in StackBackend
        self::assertInstanceOf(HashtableBackend::class, $this->getBackend($handler));
    }

    #[Test]
    public function testBuiltinDriverDoesNotStack(): void
    {
        $injector = $this->createInjector([
            'sessionhandler' => [
                'type' => 'builtin',
                'hashtable' => true,
            ],
        ]);

        $handler = $this->factory->create($injector);

        self::assertInstanceOf(BuiltinBackend::class, $this->getBackend($handler));
    }

    #[Test]
    public function testEmptyDriverStringDefaultsToBuiltin(): void
    {
        $injector = $this->createInjector([
            'sessionhandler' => ['type' => ''],
        ]);

        $handler = $this->factory->create($injector);

        self::assertInstanceOf(BuiltinBackend::class, $this->getBackend($handler));
    }
}
