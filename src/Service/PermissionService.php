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

use Horde\Core\Service\Exception\PermissionNotFoundException;

/**
 * Permission service interface
 *
 * Modern service interface for permission management, wrapping legacy
 * Horde_Perms backends (SQL, Null).
 *
 * Handles hierarchical permission trees with colon-separated names
 * (e.g., 'horde:turba:contact:123').
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface PermissionService
{
    /**
     * List all permissions
     *
     * Returns flat list of all permission names in the system.
     *
     * @return array Array of permission names
     */
    public function listAll(): array;

    /**
     * Get permission tree structure
     *
     * Returns hierarchical tree of permissions with parent-child relationships.
     *
     * @return array Tree structure mapping IDs to names
     */
    public function getTree(): array;

    /**
     * Get permission by name
     *
     * Returns permission details with expanded permission matrix (boolean flags
     * instead of bitmasks).
     *
     * @param string $name Permission name (e.g., 'horde:turba:contact:123')
     * @return array Permission details with expanded matrix
     * @throws PermissionNotFoundException If permission doesn't exist
     */
    public function get(string $name): array;

    /**
     * Check if permission exists
     *
     * @param string $name Permission name
     * @return bool True if permission exists
     */
    public function exists(string $name): bool;

    /**
     * Get parent permissions for a permission
     *
     * Returns array of parent permission names in hierarchical order.
     *
     * @param string $name Child permission name
     * @return array Array of parent permission names
     */
    public function getParents(string $name): array;

    /**
     * Create new permission
     *
     * @param string $name Permission name
     * @param string $type Permission type ('matrix' or 'boolean')
     * @param array $data Permission data (users, groups, default, etc.)
     * @return void
     */
    public function create(string $name, string $type = 'matrix', array $data = []): void;

    /**
     * Update existing permission
     *
     * @param string $name Permission name
     * @param array $data Permission data (users, groups, default, etc.)
     * @return void
     * @throws PermissionNotFoundException If permission doesn't exist
     */
    public function update(string $name, array $data): void;

    /**
     * Delete permission
     *
     * @param string $name Permission name
     * @param bool $force If true, also delete all child permissions
     * @return void
     * @throws PermissionNotFoundException If permission doesn't exist
     */
    public function delete(string $name, bool $force = false): void;

    /**
     * Check if user has specific permission
     *
     * @param string $name Permission name
     * @param string $user Username
     * @param array $requiredPerms Required permission flags (e.g., ['read', 'edit'])
     * @return bool True if user has all required permissions
     */
    public function hasPermission(string $name, string $user, array $requiredPerms): bool;

    /**
     * Get user's permissions for a resource
     *
     * Returns expanded permission flags.
     *
     * @param string $name Permission name
     * @param string $user Username
     * @return array Permission flags (show, read, edit, delete)
     */
    public function getUserPermissions(string $name, string $user): array;
}
