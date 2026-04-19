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

namespace Horde\Core\Test\Unit\Service;

use Horde\Core\Service\FileGroupService;
use Horde\Core\Service\Exception\GroupNotFoundException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

/**
 * Tests for FileGroupService
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(FileGroupService::class)]
class FileGroupServiceTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'group_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    private function createFixtureFile(string $content): void
    {
        file_put_contents($this->tempFile, $content);
    }

    public function testParseBasicGroupFile(): void
    {
        $this->createFixtureFile(
            <<<EOF
                root:x:0:
                daemon:x:1:
                bin:x:2:root,daemon
                sys:x:3:
                EOF
        );

        $service = new FileGroupService($this->tempFile, useGid: false);

        $this->assertTrue($service->exists('root'));
        $this->assertTrue($service->exists('daemon'));
        $this->assertTrue($service->exists('bin'));
        $this->assertTrue($service->exists('sys'));
    }

    public function testParseGroupWithMembers(): void
    {
        $this->createFixtureFile(
            <<<EOF
                developers:x:1001:alice,bob,charlie
                EOF
        );

        $service = new FileGroupService($this->tempFile);

        $group = $service->get('developers');
        $this->assertEquals('developers', $group->name);
        $this->assertEquals(['alice', 'bob', 'charlie'], $group->members);
    }

    public function testParseGroupWithNoMembers(): void
    {
        $this->createFixtureFile(
            <<<EOF
                empty:x:1001:
                EOF
        );

        $service = new FileGroupService($this->tempFile);

        $group = $service->get('empty');
        $this->assertEmpty($group->members);
    }

    public function testUseGidAsId(): void
    {
        $this->createFixtureFile(
            <<<EOF
                root:x:0:
                developers:x:1001:alice
                EOF
        );

        $service = new FileGroupService($this->tempFile, useGid: true);

        // Access by GID
        $group = $service->get('0');
        $this->assertEquals('root', $group->name);

        $group = $service->get('1001');
        $this->assertEquals('developers', $group->name);
    }

    public function testUseNameAsIdDefault(): void
    {
        $this->createFixtureFile(
            <<<EOF
                developers:x:1001:alice
                EOF
        );

        $service = new FileGroupService($this->tempFile, useGid: false);

        $group = $service->get('developers');
        $this->assertEquals('developers', $group->id);
        $this->assertEquals('developers', $group->name);
    }

    public function testSkipComments(): void
    {
        $this->createFixtureFile(
            <<<EOF
                # This is a comment
                root:x:0:
                # Another comment
                daemon:x:1:
                EOF
        );

        $service = new FileGroupService($this->tempFile);

        $result = $service->listAll();
        $this->assertCount(2, $result->groups);
    }

    public function testSkipMalformedLines(): void
    {
        $this->createFixtureFile(
            <<<EOF
                root:x:0:
                malformed_line
                daemon:x:1:
                invalid:only_two_fields
                sys:x:3:
                EOF
        );

        $service = new FileGroupService($this->tempFile);

        $result = $service->listAll();
        $this->assertCount(3, $result->groups); // Only valid lines
    }

    public function testListAllPagination(): void
    {
        $lines = [];
        for ($i = 1; $i <= 25; $i++) {
            $lines[] = "group$i:x:$i:";
        }
        $this->createFixtureFile(implode("\n", $lines));

        $service = new FileGroupService($this->tempFile);

        // Page 1
        $result = $service->listAll(1, 10);
        $this->assertCount(10, $result->groups);
        $this->assertEquals(25, $result->total);
        $this->assertTrue($result->hasNext);
        $this->assertFalse($result->hasPrev);

        // Page 3 (last page, partial)
        $result = $service->listAll(3, 10);
        $this->assertCount(5, $result->groups);
        $this->assertFalse($result->hasNext);
        $this->assertTrue($result->hasPrev);
    }

    public function testGetGroupByName(): void
    {
        $this->createFixtureFile(
            <<<EOF
                admins:x:100:root
                users:x:101:alice,bob
                EOF
        );

        $service = new FileGroupService($this->tempFile);

        $group = $service->get('users');
        $this->assertEquals('users', $group->name);
        $this->assertEquals(['alice', 'bob'], $group->members);
    }

    public function testGetNonExistentGroupThrows(): void
    {
        $this->createFixtureFile("root:x:0:\n");

        $service = new FileGroupService($this->tempFile);

        $this->expectException(GroupNotFoundException::class);
        $service->get('nonexistent');
    }

    public function testExistsByName(): void
    {
        $this->createFixtureFile("developers:x:1001:\n");

        $service = new FileGroupService($this->tempFile);

        $this->assertTrue($service->exists('developers'));
        $this->assertFalse($service->exists('nonexistent'));
    }

    public function testGetMembers(): void
    {
        $this->createFixtureFile("team:x:1001:alice,bob,charlie\n");

        $service = new FileGroupService($this->tempFile);

        $members = $service->getMembers('team');
        $this->assertEquals(['alice', 'bob', 'charlie'], $members);
    }

    public function testIsReadOnly(): void
    {
        $this->createFixtureFile("root:x:0:\n");

        $service = new FileGroupService($this->tempFile);

        $this->assertTrue($service->isReadOnly());
    }

    public function testCreateThrowsReadOnly(): void
    {
        $this->createFixtureFile("root:x:0:\n");

        $service = new FileGroupService($this->tempFile);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('read-only');
        $service->create('newgroup');
    }

    public function testDeleteThrowsReadOnly(): void
    {
        $this->createFixtureFile("root:x:0:\n");

        $service = new FileGroupService($this->tempFile);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('read-only');
        $service->delete('root');
    }

    public function testAddMemberThrowsReadOnly(): void
    {
        $this->createFixtureFile("root:x:0:\n");

        $service = new FileGroupService($this->tempFile);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('read-only');
        $service->addMember('root', 'alice');
    }

    public function testFileNotFoundThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not found');
        new FileGroupService('/nonexistent/file');
    }

    public function testEmptyFileReturnsEmptyList(): void
    {
        $this->createFixtureFile('');

        $service = new FileGroupService($this->tempFile);

        $result = $service->listAll();
        $this->assertEmpty($result->groups);
        $this->assertEquals(0, $result->total);
    }

    public function testTrailingCommaInMemberList(): void
    {
        $this->createFixtureFile("team:x:1001:alice,bob,\n");

        $service = new FileGroupService($this->tempFile);

        $members = $service->getMembers('team');
        // Empty strings should be filtered out
        $this->assertCount(2, $members);
        $this->assertEquals(['alice', 'bob'], $members);
    }
}
