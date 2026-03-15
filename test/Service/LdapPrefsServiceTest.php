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
    private Horde_Ldap $ldapAdapter;
    private LdapPrefsService $service;

    protected function setUp(): void
    {
        $this->ldapService = $this->createMock(HordeLdapService::class);
        $this->ldapAdapter = $this->createMock(Horde_Ldap::class);

        $this->ldapService->method('getAdapter')->willReturn($this->ldapAdapter);

        $this->service = new LdapPrefsService(
            $this->ldapService,
            'ou=users,dc=example,dc=com'
        );
    }

    public function testGetValueFromHordePerson(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->with('alice')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createMock(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(1);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')
            ->with('hordePrefHordeTheme', 'single')
            ->willReturn('silver');

        $search->method('shiftEntry')->willReturn($entry);

        $this->ldapAdapter->method('search')->willReturn($search);

        $value = $this->service->getValue('alice', 'horde', 'theme');

        $this->assertEquals('silver', $value);
    }

    public function testGetValueFromUserEntry(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->with('alice')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createMock(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(0);

        $this->ldapAdapter->method('search')->willReturn($search);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')
            ->with('hordePrefHordeTheme', 'single')
            ->willReturn('blue');

        $this->ldapAdapter->method('getEntry')->willReturn($entry);

        $value = $this->service->getValue('alice', 'horde', 'theme');

        $this->assertEquals('blue', $value);
    }

    public function testGetValueNotFound(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->willThrowException(new \Horde_Ldap_Exception('User not found'));

        $value = $this->service->getValue('nonexistent', 'horde', 'theme');

        $this->assertNull($value);
    }

    public function testSetValueNewHordePerson(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->with('alice')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createMock(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(0);

        $this->ldapAdapter->method('search')->willReturn($search);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')
            ->with('objectClass')
            ->willReturn(['inetOrgPerson']);

        $entry->expects($this->exactly(2))
            ->method('replace');
        $entry->expects($this->once())
            ->method('update');

        $this->ldapAdapter->method('getEntry')->willReturn($entry);

        $this->service->setValue('alice', 'horde', 'theme', 'silver');
    }

    public function testSetValueExistingHordePerson(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->with('alice')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createMock(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(1);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->expects($this->once())
            ->method('replace')
            ->with(['hordePrefHordeTheme' => 'silver']);
        $entry->expects($this->once())
            ->method('update');

        $search->method('shiftEntry')->willReturn($entry);

        $this->ldapAdapter->method('search')->willReturn($search);

        $this->service->setValue('alice', 'horde', 'theme', 'silver');
    }

    public function testDeleteValue(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->with('alice')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createMock(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(1);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->expects($this->once())
            ->method('delete')
            ->with(['hordePrefHordeTheme' => []]);
        $entry->expects($this->once())
            ->method('update');

        $search->method('shiftEntry')->willReturn($entry);

        $this->ldapAdapter->method('search')->willReturn($search);

        $this->service->deleteValue('alice', 'horde', 'theme');
    }

    public function testDeleteValueUserNotFound(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->willThrowException(new \Horde_Ldap_Exception('User not found'));

        // Should not throw exception
        $this->service->deleteValue('nonexistent', 'horde', 'theme');
        $this->assertTrue(true);
    }

    public function testGetAllInScope(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->with('alice')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createMock(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(1);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValues')->willReturn([
            'cn' => ['Alice'],
            'hordePrefHordeTheme' => ['silver'],
            'hordePrefHordeLanguage' => ['en_US'],
            'hordePrefImpLayout' => ['wide'],
        ]);

        $search->method('shiftEntry')->willReturn($entry);

        $this->ldapAdapter->method('search')->willReturn($search);

        $prefs = $this->service->getAllInScope('alice', 'horde');

        $this->assertArrayHasKey('theme', $prefs);
        $this->assertEquals('silver', $prefs['theme']);
        $this->assertArrayHasKey('language', $prefs);
        $this->assertEquals('en_US', $prefs['language']);
        $this->assertArrayNotHasKey('layout', $prefs); // Different scope (imp)
    }

    public function testGetAllInScopeEmpty(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->with('alice')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createMock(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(1);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValues')->willReturn([
            'cn' => ['Alice'],
        ]);

        $search->method('shiftEntry')->willReturn($entry);

        $this->ldapAdapter->method('search')->willReturn($search);

        $prefs = $this->service->getAllInScope('alice', 'horde');

        $this->assertEmpty($prefs);
    }

    public function testExistsTrue(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->with('alice')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $this->assertTrue($this->service->exists('alice'));
    }

    public function testExistsFalse(): void
    {
        $this->ldapAdapter->method('findUserDN')
            ->willThrowException(new \Horde_Ldap_Exception('User not found'));

        $this->assertFalse($this->service->exists('nonexistent'));
    }

    public function testAttributeNaming(): void
    {
        // Test that attribute names are properly formatted
        $this->ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createMock(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(0);
        $this->ldapAdapter->method('search')->willReturn($search);

        $entry = $this->createMock(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn(['inetOrgPerson']);
        $entry->expects($this->exactly(2))->method('replace')
            ->withConsecutive(
                [['objectClass' => $this->anything()]],
                [['hordePrefImpSentFolder' => '/Sent']] // Capital I for Imp, capital S for SentFolder
            );
        $entry->method('update');

        $this->ldapAdapter->method('getEntry')->willReturn($entry);

        $this->service->setValue('alice', 'imp', 'sentFolder', '/Sent');
    }
}
