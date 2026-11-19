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

namespace Horde\Core\Service;

use Horde\Core\Config\PrefsConfigLoader;
use Horde\Core\Prefs\Storage\PrefsConfigLoaderStorage;
use Horde_Db_Adapter;
use Horde_Db_Exception;
use Horde_Prefs;
use Horde_Prefs_Scope;
use Horde_Prefs_Storage_Sql;
use RuntimeException;

/**
 * SQL-based preferences service implementation
 *
 * Provides prefs storage using SQL backend (horde_prefs table).
 * Wraps legacy Horde_Prefs and Horde_Prefs_Storage_Sql classes
 * with modern DI-friendly interface.
 *
 * ## Cascade order
 *
 * createPrefs() wires two drivers into Horde_Prefs in this order:
 *
 *   [PrefsConfigLoaderStorage, Horde_Prefs_Storage_Sql]
 *
 * PrefsConfigLoaderStorage (config layer) applies defaults and locked flags
 * from the five-layer file cascade (vendor -> base -> prefs.d/* -> local ->
 * vhost) without touching $GLOBALS['registry'].  Horde_Prefs_Storage_Sql
 * (user layer) then overwrites unlocked keys with per-user DB values.
 *
 * getValue(), exists(), and isLocked() all go through createPrefs() and
 * therefore see the full cascade correctly.
 *
 * getAllInScope() builds the same merged view without instantiating
 * Horde_Prefs: it starts from the config defaults returned by
 * PrefsConfigLoader, overlays DB rows for non-locked keys, and returns
 * the result.  This ensures config-only defaults appear in the output
 * and locked prefs are never shadowed by stale DB rows.
 *
 * setValue() checks the config lock flag via PrefsConfigLoader before
 * writing, preventing locked prefs from accumulating stale DB rows.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class SqlPrefsService implements PrefsService
{
    use PrefsConfigCascadeTrait;
    /**
     * Constructor
     *
     * @param Horde_Db_Adapter  $db               Database adapter
     * @param PrefsConfigLoader $prefsConfigLoader Config cascade loader
     * @param string            $table             Prefs table name
     */
    public function __construct(
        private Horde_Db_Adapter $db,
        private PrefsConfigLoader $prefsConfigLoader,
        private string $table = 'horde_prefs'
    ) {}

    /**
     * Get preference value
     *
     * Delegates to Horde_Prefs which applies the full config+SQL cascade.
     *
     * @param string $uid   User ID
     * @param string $scope App name
     * @param string $key   Preference key
     * @return mixed|null Preference value or null if not found
     */
    public function getValue(string $uid, string $scope, string $key)
    {
        $prefs = $this->createPrefs($uid, $scope);

        if (!isset($prefs[$key])) {
            return null;
        }

        return $prefs->getValue($key);
    }

    /**
     * Set preference value
     *
     * Writes $value to the SQL backend for the given user/scope/key.
     * Throws RuntimeException if the preference is locked in the config
     * cascade. Writing a locked pref would create a stale DB row that
     * could resurface if the lock is later removed.
     *
     * @param string $uid   User ID
     * @param string $scope App name
     * @param string $key   Preference key
     * @param mixed  $value Preference value
     * @throws RuntimeException If the preference is locked in config
     */
    public function setValue(string $uid, string $scope, string $key, $value): void
    {
        $this->assertNotLocked($scope, $key);

        $storage = new Horde_Prefs_Storage_Sql($uid, [
            'db'    => $this->db,
            'table' => $this->table,
        ]);

        $scopeObj = new Horde_Prefs_Scope($scope);
        $scopeObj->set($key, $value);

        $storage->store($scopeObj);
    }

    /**
     * Delete preference
     *
     * @param string $uid   User ID
     * @param string $scope App name
     * @param string $key   Preference key
     */
    public function deleteValue(string $uid, string $scope, string $key): void
    {
        $storage = new Horde_Prefs_Storage_Sql($uid, [
            'db'    => $this->db,
            'table' => $this->table,
        ]);

        $storage->remove($scope, $key);
    }

    /**
     * Get all preferences for user in scope
     *
     * Returns the merged view that mirrors what getValue() produces for each
     * individual key:
     *
     *   1. Config defaults. All non-UI prefs with a 'value' defined in the
     *      five-layer file cascade are seeded into the result.
     *   2. Locked prefs. Kept at their config value; any matching DB row is
     *      silently ignored (same as Horde_Prefs::getValue() behaviour).
     *   3. Unlocked prefs. DB value overlays the config default when present.
     *   4. DB-only prefs. Prefs that exist in the DB but have no config
     *      definition are included as-is (user-defined custom prefs).
     *
     * Unlike the previous raw-SQL implementation, config-only defaults (prefs
     * with no user DB row) now appear in the output.
     *
     * @param string $uid   User ID
     * @param string $scope App name
     * @return array<string, mixed> key => effective value
     */
    public function getAllInScope(string $uid, string $scope): array
    {
        // --- Step 1: seed from config defaults ---
        ['defaults' => $result, 'locked' => $locked] = $this->buildConfigSeed($scope);

        // --- Step 2: overlay DB values, honouring locks ---
        try {
            $query  = 'SELECT pref_name, pref_value FROM ' . $this->table
                    . ' WHERE pref_uid = ? AND pref_scope = ?';
            $rows   = $this->db->select($query, [$uid, $scope]);
            $columns = $this->db->columns($this->table);

            foreach ($rows as $row) {
                $key = trim($row['pref_name']);
                if (isset($locked[$key])) {
                    // Config lock wins. Do not let a stale DB row shadow it
                    continue;
                }
                $result[$key] = $columns['pref_value']->binaryToString($row['pref_value']);
            }
        } catch (Horde_Db_Exception) {
            // DB unavailable: return config defaults only
        }

        return $result;
    }

    /**
     * Check if preference exists
     *
     * @param string $uid   User ID
     * @param string $scope App name
     * @param string $key   Preference key
     * @return bool True if preference exists
     */
    public function exists(string $uid, string $scope, string $key): bool
    {
        $prefs = $this->createPrefs($uid, $scope);
        return isset($prefs[$key]);
    }

    /**
     * Create Horde_Prefs instance for user/scope
     *
     * Driver stack:
     *   [PrefsConfigLoaderStorage, Horde_Prefs_Storage_Sql]
     *
     * PrefsConfigLoaderStorage reads defaults and locked flags from the
     * five-layer config file cascade via PrefsConfigLoader — no
     * $GLOBALS['registry'] required.  Horde_Prefs_Storage_Sql then
     * overlays per-user DB values for unlocked prefs.
     *
     * @param string $uid   User ID
     * @param string $scope App name
     * @return Horde_Prefs Cascaded prefs instance
     */
    private function createPrefs(string $uid, string $scope): Horde_Prefs
    {
        $configDriver = new PrefsConfigLoaderStorage($uid, $this->prefsConfigLoader);

        $sqlDriver = new Horde_Prefs_Storage_Sql($uid, [
            'db'    => $this->db,
            'table' => $this->table,
        ]);

        return new Horde_Prefs($scope, [$configDriver, $sqlDriver], [
            'user' => $uid,
        ]);
    }

}
