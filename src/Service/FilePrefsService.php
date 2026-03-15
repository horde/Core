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

use RuntimeException;

/**
 * File-based preferences storage service
 *
 * Stores user preferences as serialized PHP arrays in individual files.
 * Directory structure: {directory}/{scope}/{uid}.prefs
 *
 * Format: PHP serialized array of key => value pairs per scope.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class FilePrefsService implements PrefsService
{
    /**
     * Constructor
     *
     * @param string $directory Base directory for prefs files
     * @throws RuntimeException If directory is not writable
     */
    public function __construct(
        private string $directory
    ) {
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0o700, true)) {
                throw new RuntimeException("Failed to create prefs directory: {$this->directory}");
            }
        }

        if (!is_writable($this->directory)) {
            throw new RuntimeException("Prefs directory is not writable: {$this->directory}");
        }
    }

    /**
     * Get preference value
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @return mixed|null Value or null if not found
     */
    public function getValue(string $uid, string $scope, string $key)
    {
        $data = $this->loadScopeData($uid, $scope);
        return $data[$key] ?? null;
    }

    /**
     * Set preference value
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @param mixed $value Preference value
     */
    public function setValue(string $uid, string $scope, string $key, $value): void
    {
        $data = $this->loadScopeData($uid, $scope);
        $data[$key] = $value;
        $this->saveScopeData($uid, $scope, $data);
    }

    /**
     * Delete preference
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     */
    public function deleteValue(string $uid, string $scope, string $key): void
    {
        $data = $this->loadScopeData($uid, $scope);

        if (isset($data[$key])) {
            unset($data[$key]);
            $this->saveScopeData($uid, $scope, $data);
        }
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
        return $this->loadScopeData($uid, $scope);
    }

    /**
     * Check if preference exists
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @return bool
     */
    public function exists(string $uid, string $scope, string $key): bool
    {
        $data = $this->loadScopeData($uid, $scope);
        return array_key_exists($key, $data);
    }

    /**
     * Get file path for user's scope preferences
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @return string Full file path
     */
    private function getFilePath(string $uid, string $scope): string
    {
        // Sanitize uid to prevent directory traversal
        $safeUid = basename($uid);

        // Create scope directory if needed
        $scopeDir = $this->directory . '/' . $scope;
        if (!is_dir($scopeDir)) {
            mkdir($scopeDir, 0o700, true);
        }

        return $scopeDir . '/' . $safeUid . '.prefs';
    }

    /**
     * Load preference data for user and scope
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @return array Preference data
     */
    private function loadScopeData(string $uid, string $scope): array
    {
        $file = $this->getFilePath($uid, $scope);

        if (!file_exists($file)) {
            return [];
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Failed to read prefs file: $file");
        }

        // Suppress warnings for corrupted files
        $data = @unserialize($contents);
        if ($data === false && $contents !== serialize(false)) {
            // File corrupted, return empty array
            return [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Save preference data for user and scope
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param array $data Preference data
     */
    private function saveScopeData(string $uid, string $scope, array $data): void
    {
        $file = $this->getFilePath($uid, $scope);

        $serialized = serialize($data);

        // Use LOCK_EX to prevent concurrent write corruption
        $result = file_put_contents($file, $serialized, LOCK_EX);

        if ($result === false) {
            throw new RuntimeException("Failed to write prefs file: $file");
        }

        // Set restrictive permissions (owner read/write only)
        chmod($file, 0o600);
    }
}
