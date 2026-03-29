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

use Horde\Core\Service\LdapGroupService;
use Horde\Core\Service\HordeLdapService;
use Horde\Core\Service\GroupInfo;
use Horde_Ldap;
use Horde_Ldap_Search;
use Horde_Ldap_Entry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for LdapGroupService
 *
 * @requires extension ldap
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(LdapGroupService::class)]
class LdapGroupServiceTest extends TestCase
{
    private HordeLdapService $ldapService;

    protected function setUp(): void
    {
        $this->ldapService = $this->createStub(HordeLdapService::class);
    }

    public function testListAllGroups(): void
    {
        $ldapAdapter = $this->createMock(Horde_Ldap::class);
        $search = $this->createMock(Horde_Ldap_Search::class);

        $entry1 = $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entry1->expects($this->exactly(3))->method('getValue')->willReturnCallback(function ($attr, $mode = null) {
            if ($attr === 'cn' && $mode === 'single') {
                return 'developers';
            }
            if ($attr === 'memberUid') {
                return ['alice', 'bob'];
            }
            if ($attr === 'mail' && $mode === 'single') {
                return 'dev@example.com';
            }
            return null;
        });

        $entry2 = $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entry2->expects($this->exactly(3))->method('getValue')->willReturnCallback(function ($attr, $mode = null) {
            if ($attr === 'cn' && $mode === 'single') {
                return 'admins';
            }
            if ($attr === 'memberUid') {
                return ['charlie'];
            }
            if ($attr === 'mail' && $mode === 'single') {
                return null;
            }
            return null;
        });

        $search->expects($this->exactly(3))->method('valid')
            ->willReturnOnConsecutiveCalls(true, true, false);
        $search->expects($this->exactly(2))->method('current')->willReturn($entry1, $entry2);
        $search->expects($this->exactly(2))->method('next');

        $ldapAdapter->expects($this->once())
            ->method('search')
            ->willReturn($search);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $result = $service->listAll();

        $this->assertCount(2, $result->groups);
        $this->assertEquals('developers', $result->groups[0]->name);
        $this->assertEquals(['alice', 'bob'], $result->groups[0]->members);
    }

    public function testGetGroup(): void
    {
        $ldapAdapter = $this->createMock(Horde_Ldap::class);
        $entry = $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entry->expects($this->exactly(2))->method('getValue')->willReturnCallback(function ($attr, $mode = null) {
            if ($attr === 'memberUid') {
                return ['alice', 'bob'];
            }
            if ($attr === 'mail' && $mode === 'single') {
                return 'dev@example.com';
            }
            return null;
        });

        $ldapAdapter->expects($this->once())
            ->method('getEntry')
            ->with('cn=developers,ou=groups,dc=example,dc=com')
            ->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $group = $service->get('developers');

        $this->assertEquals('developers', $group->id);
        $this->assertEquals(['alice', 'bob'], $group->members);
        $this->assertEquals('dev@example.com', $group->extra['email'] ?? '');
    }

    public function testCreateGroup(): void
    {
        $ldapAdapter = $this->createMock(Horde_Ldap::class);
        // Mock search for generating next GID - verifies search is attempted
        $search = $this->getMockBuilder(Horde_Ldap_Search::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
        $search->expects($this->once())->method('valid')->willReturn(false); // Empty result
        $ldapAdapter->method('search')->willReturn($search);

        $ldapAdapter->expects($this->once())
            ->method('add')
            ->with($this->callback(function ($entry) {
                return $entry instanceof Horde_Ldap_Entry;
            }));

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $result = $service->create('developers');

        $this->assertEquals('developers', $result->id);
    }

    public function testUpdateMembers(): void
    {
        $ldapAdapter = $this->createMock(Horde_Ldap::class);
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['memberUid' => ['alice', 'bob', 'charlie']]);
        $entry->expects($this->once())
            ->method('update');

        $ldapAdapter->expects($this->once())
            ->method('getEntry')
            ->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $service->setMembers('developers', ['alice', 'bob', 'charlie']);
    }

    public function testDeleteGroup(): void
    {
        $ldapAdapter = $this->createMock(Horde_Ldap::class);
        $ldapAdapter->expects($this->once())
            ->method('delete')
            ->with('cn=developers,ou=groups,dc=example,dc=com');

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $service->delete('developers');
    }

    public function testExistsTrue(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $entry = $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entry->expects($this->exactly(2))->method('getValue')->willReturnCallback(function ($attr, $mode = null) {
            if ($attr === 'memberUid') {
                return [];
            }
            if ($attr === 'mail' && $mode === 'single') {
                return null;
            }
            return null;
        });

        $ldapAdapter->method('getEntry')->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $this->assertTrue($service->exists('developers'));
    }

    public function testExistsFalse(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('getEntry')
            ->willThrowException(new \Horde_Ldap_Exception('Not found'));

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $this->assertFalse($service->exists('nonexistent'));
    }

    public function testAddMember(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['alice']);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['memberUid' => ['alice', 'bob']]);
        $entry->expects($this->once())
            ->method('update');

        $ldapAdapter->method('getEntry')->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $service->addMember('developers', 'bob');
    }

    public function testAddMemberAlreadyExists(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['alice', 'bob']);
        $entry->expects($this->never())->method('update');

        $ldapAdapter->method('getEntry')->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $service->addMember('developers', 'bob');
    }

    public function testRemoveMember(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['alice', 'bob']);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['memberUid' => ['alice']]);
        $entry->expects($this->once())
            ->method('update');

        $ldapAdapter->method('getEntry')->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $service->removeMember('developers', 'bob');
    }

    public function testGetMembers(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $entry = $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entry->expects($this->exactly(2))->method('getValue')->willReturnCallback(function ($attr, $mode = null) {
            if ($attr === 'memberUid') {
                return ['alice', 'bob'];
            }
            if ($attr === 'mail' && $mode === 'single') {
                return null;
            }
            return null;
        });

        $ldapAdapter->method('getEntry')->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $members = $service->getMembers('developers');

        $this->assertEquals(['alice', 'bob'], $members);
    }

    public function testAddMembers(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['alice']);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['memberUid' => ['alice', 'bob', 'charlie']]);
        $entry->expects($this->once())
            ->method('update');

        $ldapAdapter->method('getEntry')->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $service->addMembers('developers', ['bob', 'charlie']);
    }

    public function testRemoveMembers(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['alice', 'bob', 'charlie']);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['memberUid' => ['alice']]);
        $entry->expects($this->once())
            ->method('update');

        $ldapAdapter->method('getEntry')->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');

        $service->removeMembers('developers', ['bob', 'charlie']);
    }

    public function testIsReadOnly(): void
    {
        $service = new LdapGroupService($this->ldapService, 'ou=groups,dc=example,dc=com');
        $this->assertFalse($service->isReadOnly());
    }
}
