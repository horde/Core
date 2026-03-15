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
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Service;

use Horde\Core\Service\MongoPrefsService;
use Horde_Mongo_Client;
use MongoCollection;
use MongoException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * Tests for MongoPrefsService
 *
 * These tests verify the service structure and error handling.
 * Actual MongoDB integration requires mongodb extension and running server.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(MongoPrefsService::class)]
class MongoPrefsServiceTest extends TestCase
{
    public function testConstructorThrowsOnConnectionFailure(): void
    {
        if (!class_exists('MongoException')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        // Create mock that throws MongoException on selectCollection
        $mockClient = $this->createMock(Horde_Mongo_Client::class);
        $mockClient->method('selectCollection')
            ->willThrowException(new MongoException('Connection refused'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to connect to MongoDB');

        new MongoPrefsService($mockClient);
    }

    public function testGetValueThrowsOnQueryError(): void
    {
        if (!class_exists('MongoException')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $mockCollection = $this->createMock(MongoCollection::class);
        $mockCollection->method('findOne')
            ->willThrowException(new MongoException('Query failed'));
        $mockCollection->method('ensureIndex')
            ->willReturn(true);

        $mockClient = $this->createMock(Horde_Mongo_Client::class);
        $mockClient->method('selectCollection')
            ->willReturn($mockCollection);

        $service = new MongoPrefsService($mockClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MongoDB query failed');

        $service->getValue('user1', 'horde', 'theme');
    }

    public function testSetValueThrowsOnUpdateError(): void
    {
        if (!class_exists('MongoException')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $mockCollection = $this->createMock(MongoCollection::class);
        $mockCollection->method('update')
            ->willThrowException(new MongoException('Update failed'));
        $mockCollection->method('ensureIndex')
            ->willReturn(true);

        $mockClient = $this->createMock(Horde_Mongo_Client::class);
        $mockClient->method('selectCollection')
            ->willReturn($mockCollection);

        $service = new MongoPrefsService($mockClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MongoDB update failed');

        $service->setValue('user1', 'horde', 'theme', 'silver');
    }

    public function testDeleteValueThrowsOnRemoveError(): void
    {
        if (!class_exists('MongoException')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $mockCollection = $this->createMock(MongoCollection::class);
        $mockCollection->method('remove')
            ->willThrowException(new MongoException('Remove failed'));
        $mockCollection->method('ensureIndex')
            ->willReturn(true);

        $mockClient = $this->createMock(Horde_Mongo_Client::class);
        $mockClient->method('selectCollection')
            ->willReturn($mockCollection);

        $service = new MongoPrefsService($mockClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MongoDB delete failed');

        $service->deleteValue('user1', 'horde', 'theme');
    }

    public function testGetAllInScopeThrowsOnQueryError(): void
    {
        if (!class_exists('MongoException')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $mockCollection = $this->createMock(MongoCollection::class);
        $mockCollection->method('find')
            ->willThrowException(new MongoException('Query failed'));
        $mockCollection->method('ensureIndex')
            ->willReturn(true);

        $mockClient = $this->createMock(Horde_Mongo_Client::class);
        $mockClient->method('selectCollection')
            ->willReturn($mockCollection);

        $service = new MongoPrefsService($mockClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MongoDB query failed');

        $service->getAllInScope('user1', 'horde');
    }

    public function testExistsThrowsOnQueryError(): void
    {
        if (!class_exists('MongoException')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $mockCollection = $this->createMock(MongoCollection::class);
        $mockCollection->method('count')
            ->willThrowException(new MongoException('Query failed'));
        $mockCollection->method('ensureIndex')
            ->willReturn(true);

        $mockClient = $this->createMock(Horde_Mongo_Client::class);
        $mockClient->method('selectCollection')
            ->willReturn($mockCollection);

        $service = new MongoPrefsService($mockClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MongoDB query failed');

        $service->exists('user1', 'horde', 'theme');
    }

    public function testIndexCreationFailureDoesNotThrow(): void
    {
        if (!class_exists('MongoException')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $mockCollection = $this->createMock(MongoCollection::class);
        $mockCollection->method('ensureIndex')
            ->willThrowException(new MongoException('Index creation failed'));

        $mockClient = $this->createMock(Horde_Mongo_Client::class);
        $mockClient->method('selectCollection')
            ->willReturn($mockCollection);

        // Index creation failure should not throw from constructor
        // (it's logged but non-fatal)
        $service = new MongoPrefsService($mockClient);

        $this->assertInstanceOf(MongoPrefsService::class, $service);
    }

    public function testCustomCollectionName(): void
    {
        if (!class_exists('MongoException')) {
            $this->markTestSkipped('MongoDB extension not available');
        }

        $mockCollection = $this->createMock(MongoCollection::class);
        $mockCollection->method('ensureIndex')
            ->willReturn(true);

        $mockClient = $this->createMock(Horde_Mongo_Client::class);
        $mockClient->expects($this->once())
            ->method('selectCollection')
            ->with(null, 'custom_prefs')
            ->willReturn($mockCollection);

        $service = new MongoPrefsService($mockClient, 'custom_prefs');

        $this->assertInstanceOf(MongoPrefsService::class, $service);
    }
}
