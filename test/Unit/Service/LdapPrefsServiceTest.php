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

use Horde\Core\Config\PrefsConfigLoader;
use Horde\Core\Service\LdapPrefsService;
use Horde\Core\Service\PrefsConfigCascadeTrait;
use Horde\Core\Service\HordeLdapService;
use Horde_Ldap;
use Horde_Ldap_Search;
use Horde_Ldap_Entry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde_Ldap_Exception;
use RuntimeException;

/**
 * Tests for LdapPrefsService
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(LdapPrefsService::class)]
#[CoversClass(PrefsConfigCascadeTrait::class)]
class LdapPrefsServiceTest extends TestCase
{
    private HordeLdapService $ldapService;
    private PrefsConfigLoader $configLoader;
    private string $tempDir;

    protected function setUp(): void
    {
        // Use stub for ldapService - just needs to return adapter
        $this->ldapService = $this->createStub(HordeLdapService::class);

        // Empty config dir — no prefs.php files means no locked prefs
        $this->tempDir = sys_get_temp_dir() . '/ldap_prefs_test_' . uniqid();
        mkdir($this->tempDir . '/vendor/horde/horde/config', 0o755, true);
        mkdir($this->tempDir . '/config/horde', 0o755, true);
        $this->configLoader = new PrefsConfigLoader(
            $this->tempDir . '/config',
            $this->tempDir . '/vendor/horde'
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Write vendor-layer prefs.php for 'horde' scope.
     *
     * @param array<string, array<string, mixed>> $prefs
     */
    private function writeVendorPrefs(array $prefs): void
    {
        $lines = ['<?php'];
        foreach ($prefs as $name => $def) {
            $exported = var_export($def, true);
            $lines[]  = "\$_prefs['" . addslashes($name) . "'] = $exported;";
        }
        file_put_contents(
            $this->tempDir . '/vendor/horde/horde/config/prefs.php',
            implode("\n", $lines)
        );
        // Config loader caches; create a fresh instance after writing.
        $this->configLoader = new PrefsConfigLoader(
            $this->tempDir . '/config',
            $this->tempDir . '/vendor/horde'
        );
    }

    /**
     * Build LDAP stubs where findUserDN finds a user but no hordePerson entry
     * and no matching attributes exist.
     *
     * Useful for testing config-default-only paths where LDAP has nothing.
     *
     * @return HordeLdapService
     */
    private function mockLdapReturningNothing(): HordeLdapService
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createStub(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(0);
        $ldapAdapter->method('search')->willReturn($search);

        // getEntry throws — no attribute on the user entry either
        $ldapAdapter->method('getEntry')
            ->willThrowException(new Horde_Ldap_Exception('No such attribute'));

        $svc = $this->createStub(HordeLdapService::class);
        $svc->method('getAdapter')->willReturn($ldapAdapter);
        return $svc;
    }

    // -------------------------------------------------------------------
    // LDAP-only tests (no config cascade)
    // -------------------------------------------------------------------

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
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

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
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

        $value = $service->getValue('alice', 'horde', 'theme');

        $this->assertEquals('blue', $value);
    }

    public function testGetValueNotFound(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willThrowException(new Horde_Ldap_Exception('User not found'));

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

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
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

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
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

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
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

        $service->deleteValue('alice', 'horde', 'theme');
    }

    public function testDeleteValueUserNotFound(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willThrowException(new Horde_Ldap_Exception('User not found'));

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

        // Should not throw exception
        $service->deleteValue('nonexistent', 'horde', 'theme');
        $this->assertTrue(true);
    }

    public function testGetAllInScope(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->getMockBuilder(Horde_Ldap_Search::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
        $search->expects($this->once())->method('count')->willReturn(1);

        $entry = $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entry->expects($this->once())->method('getValues')->willReturn([
            'cn' => ['Alice'],
            'hordePrefhordeTheme' => ['silver'],
            'hordePrefhordeLanguage' => ['en_US'],
            'hordePrefimpLayout' => ['wide'],
        ]);

        $search->expects($this->once())->method('shiftEntry')->willReturn($entry);
        $ldapAdapter->method('search')->willReturn($search);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

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

        $search = $this->getMockBuilder(Horde_Ldap_Search::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
        $search->expects($this->once())->method('count')->willReturn(1);

        $entry = $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entry->expects($this->once())->method('getValues')->willReturn([
            'cn' => ['Alice'],
        ]);

        $search->expects($this->once())->method('shiftEntry')->willReturn($entry);
        $ldapAdapter->method('search')->willReturn($search);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

        $prefs = $service->getAllInScope('alice', 'horde');

        $this->assertEmpty($prefs);
    }

    public function testExistsTrue(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->getMockBuilder(Horde_Ldap_Search::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
        $search->expects($this->once())->method('count')->willReturn(1);

        $entry = $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entry->expects($this->once())->method('getValue')->willReturn('silver');
        $search->expects($this->once())->method('shiftEntry')->willReturn($entry);

        $ldapAdapter->method('search')->willReturn($search);

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

        $this->assertTrue($service->exists('alice', 'horde', 'theme'));
    }

    public function testExistsFalse(): void
    {
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willThrowException(new Horde_Ldap_Exception('User not found'));

        $this->ldapService->method('getAdapter')->willReturn($ldapAdapter);
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

        $this->assertFalse($service->exists('nonexistent', 'horde', 'theme'));
    }

    public function testAttributeNaming(): void
    {
        // Test that attribute names are properly formatted
        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->getMockBuilder(Horde_Ldap_Search::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
        $search->expects($this->once())->method('count')->willReturn(0);
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
        $service = new LdapPrefsService($this->ldapService, $this->configLoader, 'ou=users,dc=example,dc=com');

        $service->setValue('alice', 'imp', 'sentFolder', '/Sent');

        // Verify objectClass was set
        $this->assertArrayHasKey('objectClass', $replaceCalls[0]);

        // Verify proper attribute naming: hordePrefImpSentFolder (capital I for Imp, capital S for SentFolder)
        $this->assertArrayHasKey('hordePrefImpSentFolder', $replaceCalls[1]);
        $this->assertEquals('/Sent', $replaceCalls[1]['hordePrefImpSentFolder']);
    }

    public function testIsLockedReturnsFalseWithNoConfig(): void
    {
        // Empty config dir — no prefs.php — so nothing is locked
        $service = new LdapPrefsService(
            $this->ldapService,
            $this->configLoader,
            'ou=users,dc=example,dc=com'
        );

        $this->assertFalse($service->isLocked('alice', 'horde', 'theme'));
    }

    public function testIsLockedReturnsTrueWhenConfigLockSet(): void
    {
        // Write a vendor prefs.php that locks 'theme'
        file_put_contents(
            $this->tempDir . '/vendor/horde/horde/config/prefs.php',
            '<?php $_prefs[\'theme\'] = [\'value\' => \'corporate\', \'locked\' => true];'
        );
        // Reload — cache must be clear because we just created the file
        $loader = new PrefsConfigLoader(
            $this->tempDir . '/config',
            $this->tempDir . '/vendor/horde'
        );
        $service = new LdapPrefsService($this->ldapService, $loader, 'ou=users,dc=example,dc=com');

        $this->assertTrue($service->isLocked('alice', 'horde', 'theme'));
    }

    public function testSetValueThrowsForLockedPref(): void
    {
        file_put_contents(
            $this->tempDir . '/vendor/horde/horde/config/prefs.php',
            '<?php $_prefs[\'theme\'] = [\'value\' => \'corporate\', \'locked\' => true];'
        );
        $loader = new PrefsConfigLoader(
            $this->tempDir . '/config',
            $this->tempDir . '/vendor/horde'
        );
        // LDAP adapter must never be called when the lock check fires first
        $this->ldapService->expects($this->never())->method('getAdapter');

        $service = new LdapPrefsService($this->ldapService, $loader, 'ou=users,dc=example,dc=com');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/i');

        $service->setValue('alice', 'horde', 'theme', 'custom');
    }

    // -------------------------------------------------------------------
    // Config cascade tests (PrefsConfigCascadeTrait integration)
    // -------------------------------------------------------------------

    /**
     * getValue() must return the config default when the user has no LDAP
     * attribute for the requested pref.
     */
    public function testGetValueReturnsConfigDefaultWhenNoLdapAttribute(): void
    {
        $this->writeVendorPrefs([
            'language' => ['value' => 'de_DE', 'type' => 'select'],
        ]);

        $service = new LdapPrefsService(
            $this->mockLdapReturningNothing(),
            $this->configLoader,
            'ou=users,dc=example,dc=com'
        );

        $this->assertSame('de_DE', $service->getValue('alice', 'horde', 'language'));
    }

    /**
     * getValue() must return the config value for a locked pref even when
     * LDAP would provide a different value.
     */
    public function testGetValueReturnsConfigValueForLockedPref(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'corporate', 'type' => 'select', 'locked' => true],
        ]);

        // LDAP adapter should never be consulted for a locked pref
        $ldapService = $this->createStub(HordeLdapService::class);
        $ldapService->expects($this->never())->method('getAdapter');

        $service = new LdapPrefsService(
            $ldapService,
            $this->configLoader,
            'ou=users,dc=example,dc=com'
        );

        $this->assertSame(
            'corporate',
            $service->getValue('alice', 'horde', 'theme'),
            'Locked pref must return config value without consulting LDAP'
        );
    }

    /**
     * getValue() must return the LDAP value (not the config default) for an
     * unlocked pref when an LDAP attribute exists.
     */
    public function testGetValueLdapOverridesConfigDefault(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'silver', 'type' => 'select'],
        ]);

        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->createStub(Horde_Ldap_Search::class);
        $search->method('count')->willReturn(1);

        $entry = $this->createStub(Horde_Ldap_Entry::class);
        $entry->method('getValue')->willReturn('gold');
        $search->method('shiftEntry')->willReturn($entry);
        $ldapAdapter->method('search')->willReturn($search);

        $ldapService = $this->createStub(HordeLdapService::class);
        $ldapService->method('getAdapter')->willReturn($ldapAdapter);

        $service = new LdapPrefsService(
            $ldapService,
            $this->configLoader,
            'ou=users,dc=example,dc=com'
        );

        $this->assertSame('gold', $service->getValue('alice', 'horde', 'theme'));
    }

    /**
     * getAllInScope() must return config defaults even when the user has no
     * LDAP attributes for the requested scope.
     */
    public function testGetAllInScopeReturnsConfigDefaults(): void
    {
        $this->writeVendorPrefs([
            'theme'    => ['value' => 'silver', 'type' => 'select'],
            'language' => ['value' => 'en_US',  'type' => 'select'],
        ]);

        $service = new LdapPrefsService(
            $this->mockLdapReturningNothing(),
            $this->configLoader,
            'ou=users,dc=example,dc=com'
        );

        $all = $service->getAllInScope('alice', 'horde');

        $this->assertSame('silver', $all['theme']);
        $this->assertSame('en_US', $all['language']);
    }

    /**
     * getAllInScope() must overlay LDAP values on config defaults for unlocked
     * prefs.
     */
    public function testGetAllInScopeLdapOverridesConfigDefault(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'silver', 'type' => 'select'],
        ]);

        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->getMockBuilder(Horde_Ldap_Search::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
        $search->method('count')->willReturn(1);

        $entry = $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entry->method('getValues')->willReturn([
            'hordePrefhordeTheme' => ['gold'],
        ]);
        $search->method('shiftEntry')->willReturn($entry);
        $ldapAdapter->method('search')->willReturn($search);

        $ldapService = $this->createStub(HordeLdapService::class);
        $ldapService->method('getAdapter')->willReturn($ldapAdapter);

        $service = new LdapPrefsService(
            $ldapService,
            $this->configLoader,
            'ou=users,dc=example,dc=com'
        );

        $all = $service->getAllInScope('alice', 'horde');

        $this->assertSame(
            'gold',
            $all['theme'],
            'Unlocked pref: LDAP value must overlay config default'
        );
    }

    /**
     * getAllInScope() must keep the config value for locked prefs even when
     * an LDAP attribute carries a different value.
     */
    public function testGetAllInScopeLockPreventsLdapOverride(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'corporate', 'type' => 'select', 'locked' => true],
        ]);

        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willReturn('uid=alice,ou=users,dc=example,dc=com');

        $search = $this->getMockBuilder(Horde_Ldap_Search::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->getMock();
        $search->method('count')->willReturn(1);

        $entry = $this->getMockBuilder(Horde_Ldap_Entry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entry->method('getValues')->willReturn([
            'hordePrefhordeTheme' => ['gold'], // stale LDAP attribute
        ]);
        $search->method('shiftEntry')->willReturn($entry);
        $ldapAdapter->method('search')->willReturn($search);

        $ldapService = $this->createStub(HordeLdapService::class);
        $ldapService->method('getAdapter')->willReturn($ldapAdapter);

        $service = new LdapPrefsService(
            $ldapService,
            $this->configLoader,
            'ou=users,dc=example,dc=com'
        );

        $all = $service->getAllInScope('alice', 'horde');

        $this->assertSame(
            'corporate',
            $all['theme'],
            'Locked pref: config value must win over LDAP attribute'
        );
    }

    /**
     * getAllInScope() must exclude UI-only pref types from the result.
     */
    public function testGetAllInScopeExcludesUiOnlyPrefs(): void
    {
        $this->writeVendorPrefs([
            'theme'          => ['value' => 'silver', 'type' => 'select'],
            'display_header' => ['value' => '',        'type' => 'container'],
            'some_link'      => ['value' => '',        'type' => 'link'],
        ]);

        $service = new LdapPrefsService(
            $this->mockLdapReturningNothing(),
            $this->configLoader,
            'ou=users,dc=example,dc=com'
        );

        $all = $service->getAllInScope('alice', 'horde');

        $this->assertArrayHasKey('theme', $all);
        $this->assertArrayNotHasKey('display_header', $all);
        $this->assertArrayNotHasKey('some_link', $all);
    }

    /**
     * exists() must return true for a pref that is defined in config even
     * when the user has no LDAP attribute for it.
     */
    public function testExistsReturnsTrueForConfigOnlyPref(): void
    {
        $this->writeVendorPrefs([
            'language' => ['value' => 'de_DE', 'type' => 'select'],
        ]);

        $service = new LdapPrefsService(
            $this->mockLdapReturningNothing(),
            $this->configLoader,
            'ou=users,dc=example,dc=com'
        );

        $this->assertTrue(
            $service->exists('alice', 'horde', 'language'),
            'exists() must return true for a pref only defined in config'
        );
    }

    /**
     * getValue() for a user not found in LDAP must fall back to config
     * default rather than returning null.
     */
    public function testGetValueForMissingUserReturnsConfigDefault(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'silver', 'type' => 'select'],
        ]);

        $ldapAdapter = $this->createStub(Horde_Ldap::class);
        $ldapAdapter->method('findUserDN')
            ->willThrowException(new Horde_Ldap_Exception('User not found'));

        $ldapService = $this->createStub(HordeLdapService::class);
        $ldapService->method('getAdapter')->willReturn($ldapAdapter);

        $service = new LdapPrefsService(
            $ldapService,
            $this->configLoader,
            'ou=users,dc=example,dc=com'
        );

        $this->assertSame('silver', $service->getValue('nobody', 'horde', 'theme'));
    }
}
