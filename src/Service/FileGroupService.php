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
use RuntimeException;

/**
 * File-based group service for Unix /etc/group integration
 *
 * Parses Unix group file format (group_name:x:GID:user,list).
 * Read-only backend - groups managed by system tools.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class FileGroupService implements GroupService
{
    /**
     * Parsed groups from file
     *
     * @var array<string, GroupInfo>
     */
    private array $groups = [];

    /**
     * Constructor
     *
     * @param string $file Path to group file (default: /etc/group)
     * @param bool $useGid Use numeric GID as ID instead of group name
     * @throws RuntimeException If file cannot be read
     */
    public function __construct(
        private string $file = '/etc/group',
        private bool $useGid = false
    ) {
        $this->loadGroups();
    }

    /**
     * Load and parse the group file
     *
     * @throws RuntimeException
     */
    private function loadGroups(): void
    {
        if (!file_exists($this->file)) {
            throw new RuntimeException("Group file '{$this->file}' not found");
        }

        if (!is_readable($this->file)) {
            throw new RuntimeException("Group file '{$this->file}' is not readable");
        }

        $lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException("Failed to read group file '{$this->file}'");
        }

        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Format: group_name:encrypted_passwd:GID:user_list
            $parts = explode(':', $line, 4);
            if (count($parts) < 3) {
                continue; // Skip malformed lines
            }

            [$name, $pass, $gid] = $parts;
            $users = isset($parts[3]) && $parts[3] !== ''
                ? explode(',', trim($parts[3]))
                : [];

            // Use GID or group name as ID based on config
            $id = $this->useGid ? $gid : $name;

            $this->groups[$id] = new GroupInfo(
                id: $id,
                name: $name,
                members: array_values(array_filter($users)) // Remove empty strings
            );
        }
    }

    /**
     * List all groups with pagination
     *
     * @param int $page Page number (1-indexed)
     * @param int $perPage Number of groups per page
     * @return GroupListResult Paginated result
     */
    public function listAll(int $page = 1, int $perPage = 50): GroupListResult
    {
        $total = count($this->groups);
        $offset = ($page - 1) * $perPage;

        $slice = array_slice($this->groups, $offset, $perPage, true);

        return new GroupListResult(
            groups: array_values($slice),
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
     * @throws GroupNotFoundException
     */
    public function get(string $identifier): GroupInfo
    {
        // Try by ID first
        if (isset($this->groups[$identifier])) {
            return $this->groups[$identifier];
        }

        // Try by name
        foreach ($this->groups as $group) {
            if ($group->name === $identifier) {
                return $group;
            }
        }

        throw new GroupNotFoundException("Group '$identifier' not found");
    }

    /**
     * Check if a group exists
     *
     * @param string $identifier Group ID or name
     * @return bool
     */
    public function exists(string $identifier): bool
    {
        // Check by ID
        if (isset($this->groups[$identifier])) {
            return true;
        }

        // Check by name
        foreach ($this->groups as $group) {
            if ($group->name === $identifier) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get members of a group
     *
     * @param string $identifier Group ID or name
     * @return array List of usernames
     * @throws GroupNotFoundException
     */
    public function getMembers(string $identifier): array
    {
        return $this->get($identifier)->members;
    }

    /**
     * Check if backend is read-only
     *
     * @return bool Always true (file backend is read-only)
     */
    public function isReadOnly(): bool
    {
        return true;
    }

    // ========== READ-ONLY BACKEND - WRITE METHODS THROW ===========

    /**
     * @throws RuntimeException
     */
    public function create(string $name): GroupInfo
    {
        throw new RuntimeException('File backend is read-only. Use system tools (groupadd) to create groups.');
    }

    /**
     * @throws RuntimeException
     */
    public function delete(string $identifier): void
    {
        throw new RuntimeException('File backend is read-only. Use system tools (groupdel) to delete groups.');
    }

    /**
     * @throws RuntimeException
     */
    public function addMember(string $identifier, string $username): void
    {
        throw new RuntimeException('File backend is read-only. Use system tools (gpasswd -a) to add members.');
    }

    /**
     * @throws RuntimeException
     */
    public function addMembers(string $identifier, array $usernames): void
    {
        throw new RuntimeException('File backend is read-only. Use system tools to add members.');
    }

    /**
     * @throws RuntimeException
     */
    public function removeMember(string $identifier, string $username): void
    {
        throw new RuntimeException('File backend is read-only. Use system tools (gpasswd -d) to remove members.');
    }

    /**
     * @throws RuntimeException
     */
    public function removeMembers(string $identifier, array $usernames): void
    {
        throw new RuntimeException('File backend is read-only. Use system tools to remove members.');
    }

    /**
     * @throws RuntimeException
     */
    public function setMembers(string $identifier, array $usernames): void
    {
        throw new RuntimeException('File backend is read-only. Use system tools to set members.');
    }
}
