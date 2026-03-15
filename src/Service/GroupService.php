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

use Horde\Core\Service\Exception\GroupNotFoundException;
use Horde\Core\Service\Exception\GroupExistsException;

/**
 * Group service interface
 *
 * Modern service interface for group management, wrapping legacy Horde_Group backends.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface GroupService
{
    /**
     * List all groups with pagination
     *
     * @param int $page Page number (1-indexed)
     * @param int $perPage Number of groups per page
     * @return GroupListResult Paginated result with groups and metadata
     */
    public function listAll(int $page = 1, int $perPage = 50): GroupListResult;

    /**
     * Get a specific group by identifier
     *
     * Identifier can be either the group ID (numeric for SQL, DN for LDAP)
     * or the group name. The implementation tries ID first, then name lookup.
     *
     * @param string $identifier Group ID or name
     * @return GroupInfo Group information with members
     * @throws GroupNotFoundException If group not found
     */
    public function get(string $identifier): GroupInfo;

    /**
     * Check if a group exists
     *
     * @param string $identifier Group ID or name
     * @return bool True if group exists
     */
    public function exists(string $identifier): bool;

    /**
     * Create a new group
     *
     * @param string $name Group name (must be unique)
     * @return GroupInfo The created group information
     * @throws GroupExistsException If a group with this name already exists
     */
    public function create(string $name): GroupInfo;

    /**
     * Delete a group
     *
     * @param string $identifier Group ID or name
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function delete(string $identifier): void;

    /**
     * Get members of a group
     *
     * @param string $identifier Group ID or name
     * @return array List of usernames
     * @throws GroupNotFoundException If group not found
     */
    public function getMembers(string $identifier): array;

    /**
     * Add a single member to a group (idempotent)
     *
     * If the user is already a member, this is a no-op.
     *
     * @param string $identifier Group ID or name
     * @param string $username Username to add
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function addMember(string $identifier, string $username): void;

    /**
     * Add multiple members to a group (idempotent, batch operation)
     *
     * Only adds users who are not already members.
     *
     * @param string $identifier Group ID or name
     * @param array $usernames Array of usernames to add
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function addMembers(string $identifier, array $usernames): void;

    /**
     * Remove a single member from a group (idempotent)
     *
     * If the user is not a member, this is a no-op.
     *
     * @param string $identifier Group ID or name
     * @param string $username Username to remove
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function removeMember(string $identifier, string $username): void;

    /**
     * Remove multiple members from a group (idempotent, batch operation)
     *
     * Only removes users who are currently members.
     *
     * @param string $identifier Group ID or name
     * @param array $usernames Array of usernames to remove
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function removeMembers(string $identifier, array $usernames): void;

    /**
     * Replace all members of a group
     *
     * Removes all current members and adds the specified members.
     *
     * @param string $identifier Group ID or name
     * @param array $usernames Array of usernames (new member list)
     * @return void
     * @throws GroupNotFoundException If group not found
     */
    public function setMembers(string $identifier, array $usernames): void;

    /**
     * Check if the backend is read-only
     *
     * @return bool True if backend does not support write operations
     */
    public function isReadOnly(): bool;
}
