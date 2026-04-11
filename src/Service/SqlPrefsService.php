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

use Horde_Db_Adapter;
use Horde_Prefs;
use Horde_Prefs_Storage_Sql;
use Horde_Prefs_Scope;
use Horde_Db_Exception;

/**
 * SQL-based preferences service implementation
 *
 * Provides prefs storage using SQL backend (horde_prefs table).
 * Wraps legacy Horde_Prefs and Horde_Prefs_Storage_Sql classes
 * with modern DI-friendly interface.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class SqlPrefsService implements PrefsService
{
    /**
     * Constructor
     *
     * @param Horde_Db_Adapter $db Database adapter
     * @param string $table Prefs table name (default: 'horde_prefs')
     */
    public function __construct(
        private Horde_Db_Adapter $db,
        private string $table = 'horde_prefs'
    ) {}

    /**
     * Get preference value
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @return mixed|null Preference value or null if not found
     */
    public function getValue(string $uid, string $scope, string $key)
    {
        $prefs = $this->createPrefs($uid, $scope);

        // Use array access to check existence and get value
        if (!isset($prefs[$key])) {
            return null;
        }

        return $prefs->getValue($key);
    }

    /**
     * Set preference value
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @param mixed $value Preference value
     * @return void
     */
    public function setValue(string $uid, string $scope, string $key, $value): void
    {
        // Create storage backend directly to bypass Horde_Prefs existence checks
        $storage = new Horde_Prefs_Storage_Sql($uid, [
            'db' => $this->db,
            'table' => $this->table,
        ]);

        // Create a scope object and set the value
        $scopeObj = new Horde_Prefs_Scope($scope);
        $scopeObj->set($key, $value);

        // Store directly to database
        $storage->store($scopeObj);
    }

    /**
     * Delete preference
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @return void
     */
    public function deleteValue(string $uid, string $scope, string $key): void
    {
        // Delete directly from storage backend
        $storage = new Horde_Prefs_Storage_Sql($uid, [
            'db' => $this->db,
            'table' => $this->table,
        ]);

        // Use storage's remove method
        $storage->remove($scope, $key);
    }

    /**
     * Get all preferences for user in scope
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @return array Associative array of key => value
     */
    public function getAllInScope(string $uid, string $scope): array
    {
        try {
            $query = 'SELECT pref_name, pref_value FROM ' . $this->table
                . ' WHERE pref_uid = ? AND pref_scope = ?';
            $result = $this->db->select($query, [$uid, $scope]);

            $prefs = [];
            $columns = $this->db->columns($this->table);

            foreach ($result as $row) {
                $key = trim($row['pref_name']);
                $value = $columns['pref_value']->binaryToString($row['pref_value']);
                $prefs[$key] = $value;
            }

            return $prefs;
        } catch (Horde_Db_Exception $e) {
            // Return empty array on error
            return [];
        }
    }

    /**
     * Check if preference exists
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
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
     * @param string $uid User ID
     * @param string $scope App name
     * @return Horde_Prefs Prefs instance
     */
    private function createPrefs(string $uid, string $scope): Horde_Prefs
    {
        // Create SQL storage backend
        $storage = new Horde_Prefs_Storage_Sql($uid, [
            'db' => $this->db,
            'table' => $this->table,
        ]);

        // Create prefs object with storage
        return new Horde_Prefs($scope, [
            $storage,
        ], [
            'user' => $uid,
        ]);
    }
}
