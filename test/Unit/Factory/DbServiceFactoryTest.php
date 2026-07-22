<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Factory;

use Horde\Core\Factory\DbServiceFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(DbServiceFactory::class)]
final class DbServiceFactoryTest extends TestCase
{
    public function testBuildConnectionConfigPreservesUnixProtocol(): void
    {
        $config = $this->buildConnectionConfig([
            'username' => 'horde',
            'password' => 'secret',
            'protocol' => 'unix',
            'socket' => '/var/run/postgresql/.s.PGSQL.5432',
            'database' => 'horde',
            'phptype' => 'pgsql',
        ]);

        // The unix-socket keys reach the adapter untouched.
        self::assertSame('unix', $config['protocol']);
        self::assertSame('/var/run/postgresql/.s.PGSQL.5432', $config['socket']);
        self::assertSame('horde', $config['database']);
        // Must not invent TCP defaults — PDO would dial localhost:3306.
        self::assertArrayNotHasKey('host', $config);
        self::assertArrayNotHasKey('port', $config);
    }

    public function testBuildConnectionConfigUnixWithoutSocketOmitsTcpDefaults(): void
    {
        $config = $this->buildConnectionConfig([
            'username' => 'horde',
            'password' => 'secret',
            'protocol' => 'unix',
            'database' => 'horde',
            'phptype' => 'pgsql',
        ]);

        self::assertSame('unix', $config['protocol']);
        self::assertArrayNotHasKey('host', $config);
        self::assertArrayNotHasKey('port', $config);
        self::assertArrayNotHasKey('socket', $config);
    }

    public function testBuildConnectionConfigUnixKeepsExplicitHostAndPort(): void
    {
        $config = $this->buildConnectionConfig([
            'username' => 'horde',
            'password' => 'secret',
            'protocol' => 'unix',
            'hostspec' => '/var/run/postgresql',
            'port' => 5432,
            'database' => 'horde',
            'phptype' => 'pgsql',
        ]);

        self::assertSame('/var/run/postgresql', $config['host']);
        self::assertSame(5432, $config['port']);
    }

    public function testBuildConnectionConfigRenamesHostspecToHost(): void
    {
        $config = $this->buildConnectionConfig([
            'username' => 'horde',
            'password' => 'secret',
            'hostspec' => 'db.example.test',
            'port' => 5433,
            'database' => 'horde',
            'phptype' => 'pgsql',
        ]);

        self::assertSame('db.example.test', $config['host']);
        self::assertArrayNotHasKey('hostspec', $config);
        self::assertSame(5433, $config['port']);
    }

    public function testBuildConnectionConfigDefaultsPgsqlTcpPort(): void
    {
        $config = $this->buildConnectionConfig([
            'username' => 'horde',
            'password' => 'secret',
            'protocol' => 'tcp',
            'database' => 'horde',
            'phptype' => 'pgsql',
        ]);

        self::assertSame('localhost', $config['host']);
        self::assertSame(5432, $config['port']);
    }

    public function testBuildConnectionConfigDefaultsCharset(): void
    {
        $config = $this->buildConnectionConfig([
            'username' => 'horde',
            'password' => 'secret',
            'database' => 'horde',
            'phptype' => 'mysqli',
        ]);

        self::assertSame('UTF-8', $config['charset']);
        self::assertSame('localhost', $config['host']);
        self::assertSame(3306, $config['port']);
    }

    /**
     * @param array<string, mixed> $sqlConfig
     * @return array<string, mixed>
     */
    private function buildConnectionConfig(array $sqlConfig): array
    {
        $factory = new DbServiceFactory();
        $method = (new ReflectionClass($factory))->getMethod('buildConnectionConfig');
        $method->setAccessible(true);

        return $method->invoke($factory, $sqlConfig);
    }
}
