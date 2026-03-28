<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Config\Driver;

use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\Driver\Sql\MySQLDriver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DriverRepository::class)]
class DriverRepositoryTest extends TestCase
{
    private DriverRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new DriverRepository();
    }

    public function testRegisterDriver(): void
    {
        $driver = new MySQLDriver();
        $this->repository->register($driver);

        $this->assertTrue($this->repository->has('sql', 'mysql'));
    }

    public function testGetDriver(): void
    {
        $driver = new MySQLDriver();
        $this->repository->register($driver);

        $retrieved = $this->repository->get('sql', 'mysql');
        $this->assertSame($driver, $retrieved);
    }

    public function testGetDriverThrowsExceptionForUnknownDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver not found: sql/unknown');

        $this->repository->get('sql', 'unknown');
    }

    public function testGetByType(): void
    {
        $driver = new MySQLDriver();
        $this->repository->register($driver);

        $drivers = $this->repository->getByType('sql');
        $this->assertCount(1, $drivers);
        $this->assertArrayHasKey('mysql', $drivers);
    }

    public function testGetByTypeReturnsEmptyForUnknownType(): void
    {
        $drivers = $this->repository->getByType('nosql');
        $this->assertIsArray($drivers);
        $this->assertEmpty($drivers);
    }

    public function testGetTypes(): void
    {
        $driver = new MySQLDriver();
        $this->repository->register($driver);

        $types = $this->repository->getTypes();
        $this->assertContains('sql', $types);
    }

    public function testHasDriver(): void
    {
        $driver = new MySQLDriver();
        $this->repository->register($driver);

        $this->assertTrue($this->repository->has('sql', 'mysql'));
        $this->assertFalse($this->repository->has('sql', 'pgsql'));
        $this->assertFalse($this->repository->has('nosql', 'mongodb'));
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->repository->count('sql'));

        $driver = new MySQLDriver();
        $this->repository->register($driver);

        $this->assertEquals(1, $this->repository->count('sql'));
    }
}
