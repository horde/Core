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

use Horde\Core\Service\LdapPrefsService;
use Horde\Core\Service\HordeLdapService;
use Horde_Ldap;
use Horde_Ldap_Search;
use Horde_Ldap_Entry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for LdapPrefsService
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(LdapPrefsService::class)]
class LdapPrefsServiceTest extends TestCase
{
    private HordeLdapService $ldapService;
    private LdapPrefsService $service;

    protected function setUp(): void
    {
        // Use stub for ldapService - just needs to return adapter
        $this->ldapService = $this->createStub(HordeLdapService::class);
    }

    /**
     * Create a stub of Horde_Ldap_Search without calling constructor/destructor
     */
    private function createSearchStub(): Horde_Ldap_Search
    {
        return $this->getMockBuilder(Horde_Ldap_Search::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
    }

    /**
     * Create a stub of Horde_Ldap_Entry without calling constructor
     */
    private function createEntryStub(): Horde_Ldap_Entry
    {
        return $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testGetValueFromHordePerson(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createStub(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(1);

        $entry = $this->createStub(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn('silver');

        $search->method('shiftEntry')->willReturn($entry);
        $ldapAdapter->method('search')->willReturn($search);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $value = $service->getValue('alice', 'horde', 'theme');

        $this->assertEquals('silver', $value);
    }

    public function testGetValueFromUserEntry(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createStub(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(0);
        $ldapAdapter->method('search')->willReturn($search);

        $entry = $this->createStub(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn('blue');
        $ldapAdapter->method('getEntry')->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $value = $service->getValue('alice', 'horde', 'theme');

        $this->assertEquals('blue', $value);
    }

    public function testGetValueNotFound(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willThrowException(new \Horde_Ldap_Exception('User not found'));

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $value = $service->getValue('nonexistent', 'horde', 'theme');

        $this->assertNull($value);
    }

    public function testSetValueNewHordePerson(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createStub(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(0);
        $ldapAdapter->method('search')->willReturn($search);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['inetOrgPerson']);

        $entry->expects($this->exactly(2))->method('replace');
        $entry->expects($this->once())
            ->method('update');

        $ldapAdapter->method('getEntry')->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $service->setValue('alice', 'horde', 'theme', 'silver');
    }

    public function testSetValueExistingHordePerson(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createStub(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(1);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->expects($this->once())->method('replace')
            ->with(['hordePrefHordeTheme' => 'silver']);
        $entry->expects($this->once())
            ->method('update');

        $search->method('shiftEntry')->willReturn($entry);
        $ldapAdapter->method('search')->willReturn($search);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $service->setValue('alice', 'horde', 'theme', 'silver');
    }

    public function testDeleteValue(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createStub(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(1);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->expects($this->once())
            ->method('delete')
            ->with(['hordePrefHordeTheme' => []]);
        $entry->expects($this->once())
            ->method('update');

        $search->method('shiftEntry')->willReturn($entry);
        $ldapAdapter->method('search')->willReturn($search);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $service->deleteValue('alice', 'horde', 'theme');
    }

    public function testDeleteValueUserNotFound(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willThrowException(new \Horde_Ldap_Exception('User not found'));

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        // Should not throw exception
        $service->deleteValue('nonexistent', 'horde', 'theme');
        $this->assertTrue(true);
    }

    public function testGetAllInScope(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createSearchStub();
        $search->method('count')->willReturn(1);

        $entry = $this->createEntryStub();
        $entry->method('getValues')->willReturn([
            'cn' => ['Alice'],
            'hordePrefhordeTheme' => ['silver'],
            'hordePrefhordeLanguage' => ['en_US'],
            'hordePrefimpLayout' => ['wide'],
        ]);

        $search->method('shiftEntry')->willReturn($entry);
        $ldapAdapter->method('search')->willReturn($search);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $prefs = $service->getAllInScope('alice', 'horde');

        $this->assertArrayHasKey('theme', $prefs);
        $this->assertEquals('silver', $prefs['theme']);
        $this->assertArrayHasKey('language', $prefs);
        $this->assertEquals('en_US', $prefs['language']);
        $this->assertArrayNotHasKey('layout', $prefs); // Different scope (imp)
    }

    public function testGetAllInScopeEmpty(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createSearchStub();
        $search->method('count')->willReturn(1);

        $entry = $this->createEntryStub();
        $entry->method('getValues')->willReturn([
            'cn' => ['Alice'],
        ]);

        $search->method('shiftEntry')->willReturn($entry);
        $ldapAdapter->method('search')->willReturn($search);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $prefs = $service->getAllInScope('alice', 'horde');

        $this->assertEmpty($prefs);
    }

    public function testExistsTrue(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createSearchStub();
        $search->method('count')->willReturn(1);

        $entry = $this->createEntryStub();
        $entry->method('getValue')->willReturn('silver');
        $search->method('shiftEntry')->willReturn($entry);

        $ldapAdapter->method('search')->willReturn($search);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $this->assertTrue($service->exists('alice', 'horde', 'theme'));
    }

    public function testExistsFalse(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willThrowException(new \Horde_Ldap_Exception('User not found'));

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $this->assertFalse($service->exists('nonexistent', 'horde', 'theme'));
    }

    public function testAttributeNaming(): void
    {
        // Test that attribute names are properly formatted
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createSearchStub();
        $search->method('count')->willReturn(0);
        $ldapAdapter->method('search')->willReturn($search);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['inetOrgPerson']);

        // Track replace calls to verify attribute naming
        $replaceCalls = [];
        $entry->expects($this->exactly(2))->method('replace')
            ->willReturnCallback(function ($attrs) use (&$replaceCalls) {
                $replaceCalls[] = $attrs;
            });
        $entry->method('update');

        $ldapAdapter->method('getEntry')->willReturn($entry);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, 'ou=users,dc=example,dc=com');

        $service->setValue('alice', 'imp', 'sentFolder', '/Sent');

        // Verify objectClass was set
        $this->assertArrayHasKey('objectClass', $replaceCalls[0]);

        // Verify proper attribute naming: hordePrefImpSentFolder (capital I for Imp, capital S for SentFolder)
        $this->assertArrayHasKey('hordePrefImpSentFolder', $replaceCalls[1]);
        $this->assertEquals('/Sent', $replaceCalls[1]['hordePrefImpSentFolder']);
    }
}
