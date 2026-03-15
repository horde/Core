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

/**
 * Null preferences service implementation
 *
 * In-memory only implementation for testing or session-based prefs.
 * Does not persist any data.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class NullPrefsService implements PrefsService
{
    /**
     * In-memory storage
     *
     * @var array
     */
    private array $storage = [];

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
        return $this->storage[$uid][$scope][$key] ?? null;
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
        $this->storage[$uid][$scope][$key] = $value;
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
        unset($this->storage[$uid][$scope][$key]);
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
        return $this->storage[$uid][$scope] ?? [];
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
        return isset($this->storage[$uid][$scope][$key]);
    }
}
