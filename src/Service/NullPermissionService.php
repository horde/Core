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
 * Null permission service implementation
 *
 * No-op implementation that denies all permissions.
 * Used when permissions are disabled in configuration.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class NullPermissionService implements PermissionService
{
    /**
     * List all permissions
     *
     * @return array Empty array
     */
    public function listAll(): array
    {
        return [];
    }

    /**
     * Get permission tree structure
     *
     * @return array Empty array
     */
    public function getTree(): array
    {
        return [];
    }

    /**
     * Get permission by name
     *
     * @param string $name Permission name
     * @return array Permission details
     * @throws PermissionNotFoundException Always thrown
     */
    public function get(string $name): array
    {
        throw new PermissionNotFoundException('Permissions are disabled');
    }

    /**
     * Check if permission exists
     *
     * @param string $name Permission name
     * @return bool Always false
     */
    public function exists(string $name): bool
    {
        return false;
    }

    /**
     * Get parent permissions
     *
     * @param string $name Child permission name
     * @return array Empty array
     */
    public function getParents(string $name): array
    {
        return [];
    }

    /**
     * Create new permission
     *
     * @param string $name Permission name
     * @param string $type Permission type
     * @param array $data Permission data
     * @return void
     */
    public function create(string $name, string $type = 'matrix', array $data = []): void
    {
        // No-op
    }

    /**
     * Update existing permission
     *
     * @param string $name Permission name
     * @param array $data Permission data
     * @return void
     */
    public function update(string $name, array $data): void
    {
        // No-op
    }

    /**
     * Delete permission
     *
     * @param string $name Permission name
     * @param bool $force If true, also delete children
     * @return void
     */
    public function delete(string $name, bool $force = false): void
    {
        // No-op
    }

    /**
     * Check if user has specific permission
     *
     * @param string $name Permission name
     * @param string $user Username
     * @param array $requiredPerms Required permission flags
     * @return bool Always false
     */
    public function hasPermission(string $name, string $user, array $requiredPerms): bool
    {
        return false;
    }

    /**
     * Get user's permissions for a resource
     *
     * @param string $name Permission name
     * @param string $user Username
     * @return array All permissions denied
     */
    public function getUserPermissions(string $name, string $user): array
    {
        return [
            'show' => false,
            'read' => false,
            'edit' => false,
            'delete' => false,
        ];
    }
}
