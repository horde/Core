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
use Horde\Core\Service\SqlPrefsService;
use Horde_Db_Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Horde_Db_Adapter;
use Horde_Prefs;

/**
 * Tests for SqlPrefsService
 *
 * Covers three behaviours:
 *  1. getAllInScope() merges config defaults with DB values and honours locks
 *  2. setValue() throws RuntimeException when a pref is locked
 *  3. createPrefs() uses PrefsConfigLoaderStorage (no $GLOBALS['registry'] dep)
 *
 * The suite uses a real PrefsConfigLoader backed by a temporary directory so
 * that config-layer behaviour is exercised without touching the filesystem
 * permanently. The Horde_Db_Adapter is mocked so no live DB is required.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(SqlPrefsService::class)]
class SqlPrefsServiceTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Infrastructure
    // -----------------------------------------------------------------------

    private string $tempDir;
    private string $vendorDir;
    private string $configDir;

    protected function setUp(): void
    {
        if (!class_exists(Horde_Prefs::class)) {
            $this->markTestSkipped('Horde_Prefs not available. Install horde/prefs');
        }

        $this->tempDir  = sys_get_temp_dir() . '/sql_prefs_test_' . uniqid();
        $this->vendorDir = $this->tempDir . '/vendor/horde';
        $this->configDir = $this->tempDir . '/config';

        mkdir($this->vendorDir . '/horde/config', 0o755, true);
        mkdir($this->configDir . '/horde', 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

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
            $this->vendorDir . '/horde/config/prefs.php',
            implode("\n", $lines)
        );
    }

    /**
     * Build a thin column stand-in whose binaryToString() is a no-op.
     *
     * We deliberately use an anonymous class instead of
     * createStub(\Horde_Db_Adapter_Base_Column::class) so that the test
     * works even when the horde/db package is not installed (the legacy class
     * may not be available in the autoloader during unit-test runs).
     *
     * @return object
     */
    private function makePassthroughColumn(): object
    {
        return new class {
            public function binaryToString($value)
            {
                return $value;
            }

            public function stringToTyped($value)
            {
                return $value;
            }
        };
    }

    /**
     * Build a mock Horde_Db_Adapter whose select() returns $rows.
     *
     * Each row must be ['pref_name' => string, 'pref_value' => string].
     *
     * @param array<int, array<string, string>> $rows
     * @return Horde_Db_Adapter
     */
    private function mockDbReturning(array $rows): Horde_Db_Adapter
    {
        $db = $this->createMock(Horde_Db_Adapter::class);
        $db->method('select')->willReturn($rows);
        $db->method('columns')->willReturn(['pref_value' => $this->makePassthroughColumn()]);

        return $db;
    }

    /**
     * Build a mock Horde_Db_Adapter that throws on select().
     */
    private function mockDbThrowingOnSelect(): Horde_Db_Adapter
    {
        $db = $this->createMock(Horde_Db_Adapter::class);
        $db->method('select')->willThrowException(new Horde_Db_Exception('DB offline'));
        return $db;
    }

    /**
     * Build a mock Horde_Db_Adapter that permits any read/write calls.
     *
     * INSERT/UPDATE are allowed (Horde_Prefs_Storage_Sql::store() calls one
     * of them depending on whether the row already exists). We do not assert
     * exactly which one is called. We only verify that no exception is thrown.
     */
    private function mockDbExpectingWrite(): Horde_Db_Adapter
    {
        $db = $this->createMock(Horde_Db_Adapter::class);
        $db->method('select')->willReturn([]);
        $db->method('columns')->willReturn(['pref_value' => $this->makePassthroughColumn()]);
        $db->method('insert')->willReturn(1);
        $db->method('update')->willReturn(1);

        return $db;
    }

    private function makeService(Horde_Db_Adapter $db, ?PrefsConfigLoader $loader = null): SqlPrefsService
    {
        $loader ??= new PrefsConfigLoader($this->configDir, $this->vendorDir);
        return new SqlPrefsService($db, $loader);
    }

    // -----------------------------------------------------------------------
    // getAllInScope() merges config defaults with DB values
    // -----------------------------------------------------------------------

    /**
     * When a pref has a default in prefs.php and no DB row, getAllInScope()
     * must return the config default.
     */
    public function testGetAllInScopeReturnsConfigDefault(): void
    {
        $this->writeVendorPrefs([
            'theme'    => ['value' => 'silver', 'type' => 'select'],
            'language' => ['value' => 'en_US',  'type' => 'select'],
        ]);

        // DB has no rows for this user/scope
        $service = $this->makeService($this->mockDbReturning([]));

        $all = $service->getAllInScope('alice', 'horde');

        $this->assertSame('silver', $all['theme']);
        $this->assertSame('en_US', $all['language']);
    }

    /**
     * A DB row for an unlocked pref must override the config default.
     */
    public function testGetAllInScopeDbValueOverridesDefault(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'silver', 'type' => 'select'],
        ]);

        $db = $this->mockDbReturning([
            ['pref_name' => 'theme', 'pref_value' => 'gold'],
        ]);

        $all = $this->makeService($db)->getAllInScope('alice', 'horde');

        $this->assertSame('gold', $all['theme']);
    }

    /**
     * A DB row for a locked pref must NOT override the config value. This is a new behaviour in H6.
     * The config layer wins. The stale DB row is ignored.
     */
    public function testGetAllInScopeLockPreventsDbOverride(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'corporate', 'type' => 'select', 'locked' => true],
        ]);

        $db = $this->mockDbReturning([
            ['pref_name' => 'theme', 'pref_value' => 'gold'],  // stale row
        ]);

        $all = $this->makeService($db)->getAllInScope('alice', 'horde');

        $this->assertSame(
            'corporate',
            $all['theme'],
            'Locked pref: config value must win over stale DB row'
        );
    }

    /**
     * A DB row for a pref that has no config definition (e.g. deprecated or
     * app-specific) must still appear in the result.
     */
    public function testGetAllInScopeIncludesDbOnlyPrefs(): void
    {
        // Empty config
        $db = $this->mockDbReturning([
            ['pref_name' => 'custom_flag', 'pref_value' => '1'],
        ]);

        $all = $this->makeService($db)->getAllInScope('alice', 'horde');

        $this->assertArrayHasKey('custom_flag', $all);
        $this->assertSame('1', $all['custom_flag']);
    }

    /**
     * UI-only pref types (link, prefslink, rawhtml, container, special) must
     * not appear in getAllInScope() results even if they have a 'value' key.
     */
    public function testGetAllInScopeExcludesUiOnlyPrefs(): void
    {
        $this->writeVendorPrefs([
            'theme'          => ['value' => 'silver', 'type' => 'select'],
            'display_header' => ['value' => '',        'type' => 'container'],
            'some_link'      => ['value' => '',        'type' => 'link'],
        ]);

        $all = $this->makeService($this->mockDbReturning([]))->getAllInScope('alice', 'horde');

        $this->assertArrayHasKey('theme', $all);
        $this->assertArrayNotHasKey('display_header', $all);
        $this->assertArrayNotHasKey('some_link', $all);
    }

    /**
     * When the DB raises an exception getAllInScope() must gracefully fall
     * back to config defaults only (no rethrow).
     */
    public function testGetAllInScopeFallsBackToConfigOnDbError(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'silver', 'type' => 'select'],
        ]);

        $all = $this->makeService($this->mockDbThrowingOnSelect())->getAllInScope('alice', 'horde');

        $this->assertSame(
            'silver',
            $all['theme'],
            'Config default should still be returned when DB is unavailable'
        );
    }

    /**
     * getAllInScope() used to return an empty array when there were no DB rows,
     * silently hiding every config default. Verify the pre-fix regression is gone.
     */
    public function testGetAllInScopeIsNotEmptyWhenNoDbRows(): void
    {
        $this->writeVendorPrefs([
            'theme'    => ['value' => 'silver', 'type' => 'select'],
            'language' => ['value' => 'en_US',  'type' => 'select'],
        ]);

        $all = $this->makeService($this->mockDbReturning([]))->getAllInScope('alice', 'horde');

        $this->assertNotEmpty($all, 'Config defaults must appear even when no DB rows exist');
    }

    // -----------------------------------------------------------------------
    // setValue() refuses writes to locked prefs
    // -----------------------------------------------------------------------

    /**
     * setValue() must throw RuntimeException when the pref is locked in config.
     */
    public function testSetValueThrowsForLockedPref(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'corporate', 'type' => 'select', 'locked' => true],
        ]);

        // DB adapter should never be called for a locked pref
        $db = $this->createMock(Horde_Db_Adapter::class);
        $db->expects($this->never())->method('insert');
        $db->expects($this->never())->method('update');

        $service = $this->makeService($db);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/i');

        $service->setValue('alice', 'horde', 'theme', 'custom');
    }

    /**
     * setValue() must succeed (not throw) for an unlocked pref.
     */
    public function testSetValueSucceedsForUnlockedPref(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'silver', 'type' => 'select'],  // not locked
        ]);

        $service = $this->makeService($this->mockDbExpectingWrite());

        // Must not throw
        $service->setValue('alice', 'horde', 'theme', 'gold');
        $this->addToAssertionCount(1);  // explicit assertion: no exception
    }

    /**
     * setValue() must succeed for a pref not mentioned in config at all.
     * Unknown prefs are not locked by definition.
     */
    public function testSetValueSucceedsForUnknownPref(): void
    {
        // Empty vendor config dir. No prefs defined
        $service = $this->makeService($this->mockDbExpectingWrite());

        $service->setValue('alice', 'horde', 'custom_flag', '1');
        $this->addToAssertionCount(1);
    }

    // -----------------------------------------------------------------------
    // createPrefs() no longer requires $GLOBALS['registry']
    // -----------------------------------------------------------------------

    /**
     * getValue() must return the config default even when $GLOBALS['registry']
     * is absent (e.g. a PSR-15 route).  This verifies that PrefsConfigLoader-
     * Storage replaced Horde_Core_Prefs_Storage_Configuration.
     */
    public function testGetValueReturnsConfigDefaultWithoutRegistry(): void
    {
        $this->writeVendorPrefs([
            'language' => ['value' => 'de_DE', 'type' => 'select'],
        ]);

        // Ensure the global is not set for this test
        $previousRegistry = $GLOBALS['registry'] ?? null;
        unset($GLOBALS['registry']);

        try {
            // DB has no row for this key
            $service = $this->makeService($this->mockDbReturning([]));
            $value = $service->getValue('alice', 'horde', 'language');

            $this->assertSame(
                'de_DE',
                $value,
                'Config default must be visible without $GLOBALS["registry"]'
            );
        } finally {
            // Restore global state to avoid bleeding into other tests
            if ($previousRegistry !== null) {
                $GLOBALS['registry'] = $previousRegistry;
            }
        }
    }

    /**
     * isLocked() must return true for a pref locked in config even without
     * $GLOBALS['registry'].
     */
    public function testIsLockedReturnsTrueWithoutRegistry(): void
    {
        $this->writeVendorPrefs([
            'theme' => ['value' => 'corporate', 'type' => 'select', 'locked' => true],
        ]);

        $previousRegistry = $GLOBALS['registry'] ?? null;
        unset($GLOBALS['registry']);

        try {
            $service = $this->makeService($this->mockDbReturning([]));
            $this->assertTrue($service->isLocked('alice', 'horde', 'theme'));
        } finally {
            if ($previousRegistry !== null) {
                $GLOBALS['registry'] = $previousRegistry;
            }
        }
    }
}
