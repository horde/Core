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

use Horde\Core\Service\MockGroupService;
use Horde\Core\Service\Exception\GroupNotFoundException;
use Horde\Core\Service\Exception\GroupExistsException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for MockGroupService
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(MockGroupService::class)]
class MockGroupServiceTest extends TestCase
{
    private MockGroupService $service;

    protected function setUp(): void
    {
        $this->service = new MockGroupService();
    }

    public function testCreateGroup(): void
    {
        $group = $this->service->create('Developers');

        $this->assertNotEmpty($group->id);
        $this->assertEquals('Developers', $group->name);
        $this->assertEmpty($group->members);
        $this->assertFalse($this->service->isReadOnly());
    }

    public function testCreateDuplicateGroupThrows(): void
    {
        $this->service->create('Developers');

        $this->expectException(GroupExistsException::class);
        $this->service->create('Developers');
    }

    public function testGetGroupById(): void
    {
        $created = $this->service->create('Team');
        $group = $this->service->get($created->id);

        $this->assertEquals($created->id, $group->id);
        $this->assertEquals('Team', $group->name);
    }

    public function testGetGroupByName(): void
    {
        $this->service->create('Admins');
        $group = $this->service->get('Admins');

        $this->assertEquals('Admins', $group->name);
    }

    public function testGetNonExistentGroupThrows(): void
    {
        $this->expectException(GroupNotFoundException::class);
        $this->service->get('nonexistent');
    }

    public function testExistsById(): void
    {
        $group = $this->service->create('TestGroup');

        $this->assertTrue($this->service->exists($group->id));
        $this->assertFalse($this->service->exists('nonexistent'));
    }

    public function testExistsByName(): void
    {
        $this->service->create('TestGroup');

        $this->assertTrue($this->service->exists('TestGroup'));
        $this->assertFalse($this->service->exists('OtherGroup'));
    }

    public function testDeleteGroupById(): void
    {
        $group = $this->service->create('ToDelete');
        $this->assertTrue($this->service->exists($group->id));

        $this->service->delete($group->id);

        $this->assertFalse($this->service->exists($group->id));
    }

    public function testDeleteGroupByName(): void
    {
        $this->service->create('ToDelete');
        $this->assertTrue($this->service->exists('ToDelete'));

        $this->service->delete('ToDelete');

        $this->assertFalse($this->service->exists('ToDelete'));
    }

    public function testDeleteNonExistentGroupThrows(): void
    {
        $this->expectException(GroupNotFoundException::class);
        $this->service->delete('nonexistent');
    }

    public function testListAllEmpty(): void
    {
        $result = $this->service->listAll();

        $this->assertEmpty($result->groups);
        $this->assertEquals(0, $result->total);
    }

    public function testListAllWithGroups(): void
    {
        $this->service->create('Group1');
        $this->service->create('Group2');
        $this->service->create('Group3');

        $result = $this->service->listAll();

        $this->assertCount(3, $result->groups);
        $this->assertEquals(3, $result->total);
        $this->assertEquals(1, $result->page);
        $this->assertEquals(50, $result->perPage);
        $this->assertFalse($result->hasNext);
        $this->assertFalse($result->hasPrev);
    }

    public function testListAllPagination(): void
    {
        // Create 25 groups
        for ($i = 1; $i <= 25; $i++) {
            $this->service->create("Group$i");
        }

        // Page 1
        $result = $this->service->listAll(1, 10);
        $this->assertCount(10, $result->groups);
        $this->assertEquals(25, $result->total);
        $this->assertTrue($result->hasNext);
        $this->assertFalse($result->hasPrev);

        // Page 2
        $result = $this->service->listAll(2, 10);
        $this->assertCount(10, $result->groups);
        $this->assertTrue($result->hasNext);
        $this->assertTrue($result->hasPrev);

        // Page 3 (last page, partial)
        $result = $this->service->listAll(3, 10);
        $this->assertCount(5, $result->groups);
        $this->assertFalse($result->hasNext);
        $this->assertTrue($result->hasPrev);
    }

    public function testAddMember(): void
    {
        $group = $this->service->create('Team');
        $this->service->addMember($group->id, 'alice');

        $members = $this->service->getMembers($group->id);
        $this->assertContains('alice', $members);
    }

    public function testAddMemberIdempotent(): void
    {
        $group = $this->service->create('Team');
        $this->service->addMember($group->id, 'alice');
        $this->service->addMember($group->id, 'alice'); // Duplicate

        $members = $this->service->getMembers($group->id);
        $this->assertCount(1, $members);
        $this->assertEquals(['alice'], $members);
    }

    public function testAddMemberByGroupName(): void
    {
        $this->service->create('Team');
        $this->service->addMember('Team', 'bob');

        $members = $this->service->getMembers('Team');
        $this->assertContains('bob', $members);
    }

    public function testAddMemberToNonExistentGroupThrows(): void
    {
        $this->expectException(GroupNotFoundException::class);
        $this->service->addMember('nonexistent', 'alice');
    }

    public function testAddMembers(): void
    {
        $group = $this->service->create('Team');
        $this->service->addMembers($group->id, ['alice', 'bob', 'charlie']);

        $members = $this->service->getMembers($group->id);
        $this->assertCount(3, $members);
        $this->assertContains('alice', $members);
        $this->assertContains('bob', $members);
        $this->assertContains('charlie', $members);
    }

    public function testRemoveMember(): void
    {
        $group = $this->service->create('Team');
        $this->service->addMembers($group->id, ['alice', 'bob']);

        $this->service->removeMember($group->id, 'alice');

        $members = $this->service->getMembers($group->id);
        $this->assertNotContains('alice', $members);
        $this->assertContains('bob', $members);
    }

    public function testRemoveMemberIdempotent(): void
    {
        $group = $this->service->create('Team');
        $this->service->addMember($group->id, 'alice');

        $this->service->removeMember($group->id, 'alice');
        $this->service->removeMember($group->id, 'alice'); // Already removed

        $members = $this->service->getMembers($group->id);
        $this->assertEmpty($members);
    }

    public function testRemoveMembers(): void
    {
        $group = $this->service->create('Team');
        $this->service->addMembers($group->id, ['alice', 'bob', 'charlie']);

        $this->service->removeMembers($group->id, ['alice', 'charlie']);

        $members = $this->service->getMembers($group->id);
        $this->assertCount(1, $members);
        $this->assertEquals(['bob'], $members);
    }

    public function testSetMembers(): void
    {
        $group = $this->service->create('Team');
        $this->service->addMembers($group->id, ['alice', 'bob']);

        $this->service->setMembers($group->id, ['charlie', 'dave']);

        $members = $this->service->getMembers($group->id);
        $this->assertCount(2, $members);
        $this->assertContains('charlie', $members);
        $this->assertContains('dave', $members);
        $this->assertNotContains('alice', $members);
        $this->assertNotContains('bob', $members);
    }

    public function testSetMembersRemovesDuplicates(): void
    {
        $group = $this->service->create('Team');
        $this->service->setMembers($group->id, ['alice', 'bob', 'alice']); // Duplicate

        $members = $this->service->getMembers($group->id);
        $this->assertCount(2, $members);
    }

    public function testFixturesConstructor(): void
    {
        $service = new MockGroupService([
            ['name' => 'Team1', 'members' => ['alice', 'bob']],
            ['name' => 'Team2', 'members' => ['charlie']],
        ]);

        $this->assertTrue($service->exists('Team1'));
        $this->assertTrue($service->exists('Team2'));

        $members = $service->getMembers('Team1');
        $this->assertEquals(['alice', 'bob'], $members);
    }

    public function testGetMembersReturnsEmptyArrayForNewGroup(): void
    {
        $group = $this->service->create('Empty');

        $members = $this->service->getMembers($group->id);
        $this->assertIsArray($members);
        $this->assertEmpty($members);
    }
}
