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

use Horde_Group_Base;
use Horde\Core\Service\Exception\GroupNotFoundException;
use Horde\Core\Service\Exception\GroupExistsException;

/**
 * SQL-based group service implementation
 *
 * Wraps legacy Horde_Group_Base backends with modern service interface.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class SqlGroupService implements GroupService
{
    /**
     * Constructor
     *
     * @param Horde_Group_Base $backend Legacy group backend (Horde_Group_Sql, etc.)
     */
    public function __construct(
        private Horde_Group_Base $backend
    ) {}

    /**
     * List all groups with pagination
     *
     * @param int $page Page number (1-indexed)
     * @param int $perPage Number of groups per page
     * @return GroupListResult Paginated result
     */
    public function listAll(int $page = 1, int $perPage = 50): GroupListResult
    {
        // Get all groups from backend (returns [gid => name])
        $allGroups = $this->backend->listAll();
        $total = count($allGroups);

        // Calculate pagination
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($allGroups, $offset, $perPage, true);

        // Build GroupInfo objects
        $groups = [];
        foreach ($slice as $gid => $name) {
            $members = $this->backend->listUsers($gid);
            $groups[] = new GroupInfo(
                id: (string) $gid,
                name: $name,
                members: $members
            );
        }

        return new GroupListResult(
            groups: $groups,
            total: $total,
            page: $page,
            perPage: $perPage,
            hasNext: ($offset + $perPage) < $total,
            hasPrev: $page > 1
        );
    }

    /**
     * Get a specific group by identifier
     *
     * @param string $identifier Group ID or name
     * @return GroupInfo Group information
     * @throws GroupNotFoundException If group not found
     */
    public function get(string $identifier): GroupInfo
    {
        $gid = $this->resolveIdentifier($identifier);
        $data = $this->backend->getData($gid);
        $members = $this->backend->listUsers($gid);

        // Extract name - getData() returns array with 'group_name' or 'name' key
        $name = $data['group_name'] ?? $data['name'] ?? '';

        return new GroupInfo(
            id: (string) $gid,
            name: $name,
            members: $members
        );
    }

    /**
     * Check if a group exists
     *
     * @param string $identifier Group ID or name
     * @return bool True if exists
     */
    public function exists(string $identifier): bool
    {
        try {
            $this->resolveIdentifier($identifier);
            return true;
        } catch (GroupNotFoundException $e) {
            return false;
        }
    }

    /**
     * Create a new group
     *
     * @param string $name Group name
     * @return GroupInfo Created group
     * @throws GroupExistsException If group already exists
     */
    public function create(string $name): GroupInfo
    {
        // Check if group with this name already exists
        try {
            $existing = $this->get($name);
            throw new GroupExistsException("Group already exists: $name");
        } catch (GroupNotFoundException $e) {
            // Good, doesn't exist yet
        }

        // Create group (email parameter null)
        $gid = $this->backend->create($name, null);

        // Return the created group
        return $this->get((string) $gid);
    }

    /**
     * Delete a group
     *
     * @param string $identifier Group ID or name
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function delete(string $identifier): void
    {
        $gid = $this->resolveIdentifier($identifier);
        $this->backend->remove($gid);
    }

    /**
     * Get members of a group
     *
     * @param string $identifier Group ID or name
     * @return array List of usernames
     * @throws GroupNotFoundException If group not found
     */
    public function getMembers(string $identifier): array
    {
        $gid = $this->resolveIdentifier($identifier);
        return $this->backend->listUsers($gid);
    }

    /**
     * Add a single member to a group (idempotent)
     *
     * @param string $identifier Group ID or name
     * @param string $username Username to add
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function addMember(string $identifier, string $username): void
    {
        $gid = $this->resolveIdentifier($identifier);

        // Idempotent: check if already member
        $members = $this->backend->listUsers($gid);
        if (in_array($username, $members)) {
            return;  // Already a member, no-op
        }

        $this->backend->addUser($gid, $username);
    }

    /**
     * Add multiple members to a group (idempotent, batch)
     *
     * @param string $identifier Group ID or name
     * @param array $usernames Usernames to add
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function addMembers(string $identifier, array $usernames): void
    {
        $gid = $this->resolveIdentifier($identifier);
        $currentMembers = $this->backend->listUsers($gid);

        foreach ($usernames as $username) {
            if (!in_array($username, $currentMembers)) {
                $this->backend->addUser($gid, $username);
            }
        }
    }

    /**
     * Remove a single member from a group (idempotent)
     *
     * @param string $identifier Group ID or name
     * @param string $username Username to remove
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function removeMember(string $identifier, string $username): void
    {
        $gid = $this->resolveIdentifier($identifier);

        // Idempotent: check if member exists
        $members = $this->backend->listUsers($gid);
        if (!in_array($username, $members)) {
            return;  // Not a member, no-op
        }

        $this->backend->removeUser($gid, $username);
    }

    /**
     * Remove multiple members from a group (idempotent, batch)
     *
     * @param string $identifier Group ID or name
     * @param array $usernames Usernames to remove
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function removeMembers(string $identifier, array $usernames): void
    {
        $gid = $this->resolveIdentifier($identifier);
        $currentMembers = $this->backend->listUsers($gid);

        foreach ($usernames as $username) {
            if (in_array($username, $currentMembers)) {
                $this->backend->removeUser($gid, $username);
            }
        }
    }

    /**
     * Replace all members of a group
     *
     * @param string $identifier Group ID or name
     * @param array $usernames New member list
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function setMembers(string $identifier, array $usernames): void
    {
        $gid = $this->resolveIdentifier($identifier);
        $currentMembers = $this->backend->listUsers($gid);

        // Remove members not in new list
        foreach ($currentMembers as $member) {
            if (!in_array($member, $usernames)) {
                $this->backend->removeUser($gid, $member);
            }
        }

        // Add members not in current list
        foreach ($usernames as $username) {
            if (!in_array($username, $currentMembers)) {
                $this->backend->addUser($gid, $username);
            }
        }
    }

    /**
     * Check if backend is read-only
     *
     * @return bool True if read-only
     */
    public function isReadOnly(): bool
    {
        return $this->backend->readOnly();
    }

    /**
     * Resolve identifier to group ID
     *
     * Tries to resolve identifier as:
     * 1. Numeric ID (if backend->exists() returns true)
     * 2. Group name (via exact match in search results)
     *
     * @param string $identifier Group ID or name
     * @return string Group ID
     * @throws GroupNotFoundException If not found
     */
    private function resolveIdentifier(string $identifier): string
    {
        // If numeric, try as ID first
        if (is_numeric($identifier) && $this->backend->exists($identifier)) {
            return $identifier;
        }

        // Otherwise search by name (exact match only)
        foreach ($this->backend->search($identifier) as $gid => $name) {
            if ($name === $identifier) {
                return (string) $gid;
            }
        }

        throw new GroupNotFoundException("Group not found: $identifier");
    }
}
