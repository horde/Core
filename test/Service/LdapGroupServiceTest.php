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
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(LdapGroupService::class)]
class LdapGroupServiceTest extends TestCase
{
    private HordeLdapService $ldapService;
    private Horde_Ldap $ldapAdapter;
    private LdapGroupService $service;

    protected function setUp(): void
    {
        $this->ldapService = $this->createMock(HordeLdapService::class);
        $this->ldapAdapter = $this->createMock(Horde_Ldap::class);

        $this->ldapService->method('getAdapter')->willReturn($this->ldapAdapter);

        $this->service = new LdapGroupService(
            $this->ldapService,
            'ou=groups,dc=example,dc=com'
        );
    }

    public function testListAllGroups(): void
    {
        $search = $this->createMock(Horde_Ldap_Search::class);

        $entry1 = $this->createMock(Horde_Ldap_Entry::class);
        $entry1->method('getValue')->willReturnMap([
            ['cn', 'single', 'developers'],
            ['memberUid', null, ['alice', 'bob']],
            ['mail', 'single', 'dev@example.com'],
        ]);

        $entry2 = $this->createMock(Horde_Ldap_Entry::class);
        $entry2->method('getValue')->willReturnMap([
            ['cn', 'single', 'admins'],
            ['memberUid', null, ['charlie']],
            ['mail', 'single', null],
        ]);

        $search->expects($this->exactly(2))->method('valid')->willReturn(true, false);
        $search->expects($this->exactly(2))->method('current')->willReturn($entry1, $entry2);
        $search->expects($this->exactly(2))->method('next');

        $this->ldapAdapter->expects($this->once())
            ->method('search')
            ->willReturn($search);

        $result = $this->service->listAll();

        $this->assertCount(2, $result->getGroups());
        $this->assertEquals('developers', $result->getGroups()[0]->name);
        $this->assertEquals(['alice', 'bob'], $result->getGroups()[0]->members);
    }

    public function testGetGroup(): void
    {
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->will(
            $this->returnValueMap([
                ['memberUid', null, ['alice', 'bob']],
                ['mail', 'single', 'dev@example.com'],
            ])
        );

        $this->ldapAdapter->expects($this->once())
            ->method('getEntry')
            ->with('cn=developers,ou=groups,dc=example,dc=com')
            ->willReturn($entry);

        $group = $this->service->get('developers');

        $this->assertEquals('developers', $group->id);
        $this->assertEquals(['alice', 'bob'], $group->members);
        $this->assertEquals('dev@example.com', $group->extra['email'] ?? '');
    }

    public function testCreateGroup(): void
    {
        $this->ldapAdapter->expects($this->once())
            ->method('add')
            ->with($this->callback(function ($entry) {
                return $entry instanceof Horde_Ldap_Entry;
            }));

        $result = $this->service->create('developers');

        $this->assertEquals('developers', $result->id);
    }

    public function testUpdateMembers(): void
    {
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['memberUid' => ['alice', 'bob', 'charlie']]);
        $entry->expects($this->once())
            ->method('update');

        $this->ldapAdapter->expects($this->once())
            ->method('getEntry')
            ->willReturn($entry);

        $this->service->setMembers('developers', ['alice', 'bob', 'charlie']);
    }

    public function testDeleteGroup(): void
    {
        $this->ldapAdapter->expects($this->once())
            ->method('delete')
            ->with('cn=developers,ou=groups,dc=example,dc=com');

        $this->service->delete('developers');
    }

    public function testExistsTrue(): void
    {
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->will(
            $this->returnValueMap([
                ['memberUid', null, []],
                ['mail', 'single', null],
            ])
        );

        $this->ldapAdapter->method('getEntry')->willReturn($entry);

        $this->assertTrue($this->service->exists('developers'));
    }

    public function testExistsFalse(): void
    {
        $this->ldapAdapter->method('getEntry')
            ->willThrowException(new \Horde_Ldap_Exception('Not found'));

        $this->assertFalse($this->service->exists('nonexistent'));
    }

    public function testAddMember(): void
    {
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['alice']);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['memberUid' => ['alice', 'bob']]);
        $entry->expects($this->once())
            ->method('update');

        $this->ldapAdapter->method('getEntry')->willReturn($entry);

        $this->service->addMember('developers', 'bob');
    }

    public function testAddMemberAlreadyExists(): void
    {
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['alice', 'bob']);
        $entry->expects($this->never())->method('update');

        $this->ldapAdapter->method('getEntry')->willReturn($entry);

        $this->service->addMember('developers', 'bob');
    }

    public function testRemoveMember(): void
    {
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['alice', 'bob']);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['memberUid' => ['alice']]);
        $entry->expects($this->once())
            ->method('update');

        $this->ldapAdapter->method('getEntry')->willReturn($entry);

        $this->service->removeMember('developers', 'bob');
    }

    public function testGetMembers(): void
    {
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->will(
            $this->returnValueMap([
                ['memberUid', null, ['alice', 'bob']],
                ['mail', 'single', null],
            ])
        );

        $this->ldapAdapter->method('getEntry')->willReturn($entry);

        $members = $this->service->getMembers('developers');

        $this->assertEquals(['alice', 'bob'], $members);
    }

    public function testAddMembers(): void
    {
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['alice']);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['memberUid' => ['alice', 'bob', 'charlie']]);
        $entry->expects($this->once())
            ->method('update');

        $this->ldapAdapter->method('getEntry')->willReturn($entry);

        $this->service->addMembers('developers', ['bob', 'charlie']);
    }

    public function testRemoveMembers(): void
    {
        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['alice', 'bob', 'charlie']);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['memberUid' => ['alice']]);
        $entry->expects($this->once())
            ->method('update');

        $this->ldapAdapter->method('getEntry')->willReturn($entry);

        $this->service->removeMembers('developers', ['bob', 'charlie']);
    }

    public function testIsReadOnly(): void
    {
        $this->assertFalse($this->service->isReadOnly());
    }
}
