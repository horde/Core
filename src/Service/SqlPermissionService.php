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
use Horde_Perms_Base;
use Horde_Perms_Permission;
use Horde_Perms_Exception;
use Horde_Perms;
use Exception;

/**
 * SQL-based permission service implementation
 *
 * Wraps Horde_Perms_Sql backend with modern API, expanding bitmask
 * permissions to boolean flags and resolving group IDs to names.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class SqlPermissionService implements PermissionService
{
    /**
     * Constructor
     *
     * @param Horde_Perms_Base $backend Legacy permissions backend
     * @param GroupService $groupService Group service for ID/name resolution
     */
    public function __construct(
        private Horde_Perms_Base $backend,
        private GroupService $groupService
    ) {}

    /**
     * List all permissions
     *
     * @return array Array of permission names
     */
    public function listAll(): array
    {
        $tree = $this->backend->getTree();
        // Remove ROOT pseudo-permission
        unset($tree[Horde_Perms::ROOT]);
        return array_values($tree);
    }

    /**
     * Get permission tree structure
     *
     * @return array Tree structure mapping IDs to names
     */
    public function getTree(): array
    {
        return $this->backend->getTree();
    }

    /**
     * Get permission by name
     *
     * @param string $name Permission name
     * @return array Permission details with expanded matrix
     * @throws PermissionNotFoundException If permission doesn't exist
     */
    public function get(string $name): array
    {
        try {
            $perm = $this->backend->getPermission($name);
        } catch (Horde_Perms_Exception $e) {
            throw new PermissionNotFoundException("Permission not found: {$name}", 0, $e);
        }

        $data = $perm->getData();
        $type = $data['type'] ?? 'matrix';
        $isMatrix = ($type === 'matrix');

        return [
            'name' => $perm->getName(),
            'type' => $type,
            'users' => $isMatrix
                ? $this->expandUsersToArray($data['users'] ?? [])
                : $this->expandUsersToArraySimple($data['users'] ?? []),
            'groups' => $isMatrix
                ? $this->expandGroupsToArray($data['groups'] ?? [])
                : $this->expandGroupsToArraySimple($data['groups'] ?? []),
            'default' => $isMatrix
                ? $this->expandPermissionBits($data['default'] ?? 0)
                : ($data['default'] ?? null),
            'guest' => $isMatrix
                ? $this->expandPermissionBits($data['guest'] ?? 0)
                : ($data['guest'] ?? null),
            'creator' => $isMatrix
                ? $this->expandPermissionBits($data['creator'] ?? 0)
                : ($data['creator'] ?? null),
            'parents' => $this->getParents($name),
        ];
    }

    /**
     * Check if permission exists
     *
     * @param string $name Permission name
     * @return bool True if permission exists
     */
    public function exists(string $name): bool
    {
        return $this->backend->exists($name);
    }

    /**
     * Get parent permissions
     *
     * @param string $name Child permission name
     * @return array Array of parent permission names
     */
    public function getParents(string $name): array
    {
        try {
            return $this->backend->getParents($name);
        } catch (Horde_Perms_Exception $e) {
            return [];
        }
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
        $perm = $this->backend->newPermission($name, $type);
        $isMatrix = ($type === 'matrix');

        // For matrix type, compress boolean flags to bitmasks first
        // For non-matrix types, pass values through directly
        // Note: setPerm() will handle bitmask operations internally based on type

        // Set users permissions
        if (isset($data['users'])) {
            $userData = $isMatrix
                ? $this->compressUsersFromArray($data['users'])
                : $this->compressUsersFromArraySimple($data['users']);

            foreach ($userData as $username => $value) {
                $perm->setPerm(['class' => 'users', 'name' => $username], $value, false);
            }
        }

        // Set group permissions
        if (isset($data['groups'])) {
            $groupData = $isMatrix
                ? $this->compressGroupsFromArray($data['groups'])
                : $this->compressGroupsFromArraySimple($data['groups']);

            foreach ($groupData as $gid => $value) {
                $perm->setPerm(['class' => 'groups', 'name' => (string) $gid], $value, false);
            }
        }

        // Set default permission
        if (isset($data['default'])) {
            $value = $isMatrix
                ? $this->compressPermissionBits($data['default'])
                : $data['default'];
            $perm->setPerm('default', $value, false);
        }

        // Set guest permission
        if (isset($data['guest'])) {
            $value = $isMatrix
                ? $this->compressPermissionBits($data['guest'])
                : $data['guest'];
            $perm->setPerm('guest', $value, false);
        }

        // Set creator permission
        if (isset($data['creator'])) {
            $value = $isMatrix
                ? $this->compressPermissionBits($data['creator'])
                : $data['creator'];
            $perm->setPerm('creator', $value, false);
        }

        $this->backend->addPermission($perm);
    }

    /**
     * Update existing permission
     *
     * @param string $name Permission name
     * @param array $data Permission data
     * @return void
     * @throws PermissionNotFoundException If permission doesn't exist
     */
    public function update(string $name, array $data): void
    {
        try {
            $perm = $this->backend->getPermission($name);
        } catch (Horde_Perms_Exception $e) {
            throw new PermissionNotFoundException("Permission not found: {$name}", 0, $e);
        }

        // Get current permission data and type
        $permData = $perm->getData();
        $type = $permData['type'] ?? 'matrix';
        $isMatrix = ($type === 'matrix');

        // Update permissions - compress if matrix type
        if (isset($data['users'])) {
            $permData['users'] = $isMatrix
                ? $this->compressUsersFromArray($data['users'])
                : $this->compressUsersFromArraySimple($data['users']);
        }

        if (isset($data['groups'])) {
            $permData['groups'] = $isMatrix
                ? $this->compressGroupsFromArray($data['groups'])
                : $this->compressGroupsFromArraySimple($data['groups']);
        }

        if (isset($data['default'])) {
            $permData['default'] = $isMatrix
                ? $this->compressPermissionBits($data['default'])
                : $data['default'];
        }

        if (isset($data['guest'])) {
            $permData['guest'] = $isMatrix
                ? $this->compressPermissionBits($data['guest'])
                : $data['guest'];
        }

        if (isset($data['creator'])) {
            $permData['creator'] = $isMatrix
                ? $this->compressPermissionBits($data['creator'])
                : $data['creator'];
        }

        $perm->setData($permData);
        $perm->save();
    }

    /**
     * Delete permission
     *
     * @param string $name Permission name
     * @param bool $force If true, also delete children
     * @return void
     * @throws PermissionNotFoundException If permission doesn't exist
     */
    public function delete(string $name, bool $force = false): void
    {
        try {
            $perm = $this->backend->getPermission($name);
            $this->backend->removePermission($perm, $force);
        } catch (Horde_Perms_Exception $e) {
            throw new PermissionNotFoundException("Permission not found: {$name}", 0, $e);
        }
    }

    /**
     * Check if user has specific permission
     *
     * @param string $name Permission name
     * @param string $user Username
     * @param array $requiredPerms Required permission flags
     * @return bool True if user has all required permissions
     */
    public function hasPermission(string $name, string $user, array $requiredPerms): bool
    {
        $userPerms = $this->getUserPermissions($name, $user);

        foreach ($requiredPerms as $perm) {
            if (empty($userPerms[$perm])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user's permissions for a resource
     *
     * @param string $name Permission name
     * @param string $user Username
     * @return array Permission flags
     */
    public function getUserPermissions(string $name, string $user): array
    {
        try {
            $permBits = $this->backend->getPermissions($name, $user);
            return $this->expandPermissionBits((int) $permBits);
        } catch (Horde_Perms_Exception $e) {
            return ['show' => false, 'read' => false, 'edit' => false, 'delete' => false];
        }
    }

    /**
     * Expand users to array format (matrix type)
     *
     * @param array $users Map of user_id => bitmask
     * @return array Array of user objects
     */
    private function expandUsersToArray(array $users): array
    {
        $result = [];
        foreach ($users as $userId => $bits) {
            $result[] = [
                'id' => $userId,
                'permissions' => $this->expandPermissionBits($bits),
            ];
        }
        return $result;
    }

    /**
     * Expand users to array format (non-matrix type)
     *
     * @param array $users Map of user_id => value
     * @return array Array of user objects
     */
    private function expandUsersToArraySimple(array $users): array
    {
        $result = [];
        foreach ($users as $userId => $value) {
            $result[] = [
                'id' => $userId,
                'permissions' => $value,
            ];
        }
        return $result;
    }

    /**
     * Expand groups to array format (matrix type)
     *
     * Includes group name when resolvable via GroupService.
     * Loose coupling: GroupService failure doesn't fail permission read.
     *
     * @param array $groups Map of group_id => bitmask
     * @return array Array of group objects
     */
    private function expandGroupsToArray(array $groups): array
    {
        $result = [];
        foreach ($groups as $gid => $bits) {
            $groupName = null;
            try {
                $groupInfo = $this->groupService->get((string) $gid);
                $groupName = $groupInfo->name;
            } catch (Exception $e) {
                // Group not found - name stays null (loose coupling)
            }

            $result[] = [
                'id' => (string) $gid,
                'name' => $groupName,
                'permissions' => $this->expandPermissionBits($bits),
            ];
        }
        return $result;
    }

    /**
     * Expand groups to array format (non-matrix type)
     *
     * @param array $groups Map of group_id => value
     * @return array Array of group objects
     */
    private function expandGroupsToArraySimple(array $groups): array
    {
        $result = [];
        foreach ($groups as $gid => $value) {
            $groupName = null;
            try {
                $groupInfo = $this->groupService->get((string) $gid);
                $groupName = $groupInfo->name;
            } catch (Exception $e) {
                // Group not found - name stays null
            }

            $result[] = [
                'id' => (string) $gid,
                'name' => $groupName,
                'permissions' => $value,
            ];
        }
        return $result;
    }

    /**
     * Compress users from array format (matrix type)
     *
     * @param array $users Array of user objects
     * @return array Map of user_id => bitmask
     */
    private function compressUsersFromArray(array $users): array
    {
        $compressed = [];
        foreach ($users as $user) {
            if (!isset($user['id']) || !isset($user['permissions'])) {
                continue; // Skip malformed entries
            }
            $compressed[$user['id']] = $this->compressPermissionBits($user['permissions']);
        }
        return $compressed;
    }

    /**
     * Compress users from array format (non-matrix type)
     *
     * @param array $users Array of user objects
     * @return array Map of user_id => value
     */
    private function compressUsersFromArraySimple(array $users): array
    {
        $compressed = [];
        foreach ($users as $user) {
            if (!isset($user['id']) || !isset($user['permissions'])) {
                continue;
            }
            $compressed[$user['id']] = $user['permissions'];
        }
        return $compressed;
    }

    /**
     * Compress groups from array format (matrix type)
     *
     * Uses 'id' field, ignores 'name' (loose coupling).
     * If name is provided without id, try to resolve via GroupService.
     *
     * @param array $groups Array of group objects
     * @return array Map of group_id => bitmask
     */
    private function compressGroupsFromArray(array $groups): array
    {
        $compressed = [];
        foreach ($groups as $group) {
            $gid = null;

            // Prefer explicit ID
            if (isset($group['id'])) {
                $gid = $group['id'];
            } elseif (isset($group['name'])) {
                // Try to resolve name to ID
                try {
                    $groupInfo = $this->groupService->get($group['name']);
                    $gid = $groupInfo->id;
                } catch (Exception $e) {
                    continue; // Cannot resolve, skip
                }
            }

            if ($gid && isset($group['permissions'])) {
                $compressed[$gid] = $this->compressPermissionBits($group['permissions']);
            }
        }
        return $compressed;
    }

    /**
     * Compress groups from array format (non-matrix type)
     *
     * @param array $groups Array of group objects
     * @return array Map of group_id => value
     */
    private function compressGroupsFromArraySimple(array $groups): array
    {
        $compressed = [];
        foreach ($groups as $group) {
            $gid = null;

            if (isset($group['id'])) {
                $gid = $group['id'];
            } elseif (isset($group['name'])) {
                try {
                    $groupInfo = $this->groupService->get($group['name']);
                    $gid = $groupInfo->id;
                } catch (Exception $e) {
                    continue;
                }
            }

            if ($gid && isset($group['permissions'])) {
                $compressed[$gid] = $group['permissions'];
            }
        }
        return $compressed;
    }

    /**
     * Expand permission bitmask to boolean flags
     *
     * @param int $bits Permission bitmask
     * @return array Boolean flags
     */
    private function expandPermissionBits(int $bits): array
    {
        return [
            'show' => (bool) ($bits & Horde_Perms::SHOW),
            'read' => (bool) ($bits & Horde_Perms::READ),
            'edit' => (bool) ($bits & Horde_Perms::EDIT),
            'delete' => (bool) ($bits & Horde_Perms::DELETE),
        ];
    }

    /**
     * Compress boolean flags to permission bitmask
     *
     * @param array $flags Boolean flags
     * @return int Permission bitmask
     */
    private function compressPermissionBits(array $flags): int
    {
        $bits = 0;
        if (!empty($flags['show'])) {
            $bits |= Horde_Perms::SHOW;
        }
        if (!empty($flags['read'])) {
            $bits |= Horde_Perms::READ;
        }
        if (!empty($flags['edit'])) {
            $bits |= Horde_Perms::EDIT;
        }
        if (!empty($flags['delete'])) {
            $bits |= Horde_Perms::DELETE;
        }
        return $bits;
    }
}
