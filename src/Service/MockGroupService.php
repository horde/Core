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
 * Mock group service implementation for testing
 *
 * Pure in-memory implementation with no external dependencies.
 * Supports all CRUD operations. Can be pre-populated with fixture data.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class MockGroupService implements GroupService
{
    /**
     * In-memory group storage
     *
     * @var array<string, array> Map of group ID => group data
     */
    private array $groups = [];

    /**
     * Auto-increment counter for group IDs
     *
     * @var int
     */
    private int $nextId = 1;

    /**
     * Constructor
     *
     * @param array $fixtures Optional fixture data to pre-populate
     *                        Format: [[name => '...', members => [...]], ...]
     */
    public function __construct(array $fixtures = [])
    {
        foreach ($fixtures as $fixture) {
            $this->create($fixture['name'] ?? 'Group');
            if (!empty($fixture['members'])) {
                $id = 'group_' . ($this->nextId - 1);
                $this->groups[$id]['members'] = $fixture['members'];
            }
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

        $groups = [];
        foreach ($slice as $id => $data) {
            $groups[] = new GroupInfo(
                id: $id,
                name: $data['name'],
                members: $data['members']
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
     * @throws GroupNotFoundException
     */
    public function get(string $identifier): GroupInfo
    {
        // Try by ID first
        if (isset($this->groups[$identifier])) {
            return new GroupInfo(
                id: $identifier,
                name: $this->groups[$identifier]['name'],
                members: $this->groups[$identifier]['members']
            );
        }

        // Try by name
        foreach ($this->groups as $id => $data) {
            if ($data['name'] === $identifier) {
                return new GroupInfo(
                    id: $id,
                    name: $data['name'],
                    members: $data['members']
                );
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
        foreach ($this->groups as $data) {
            if ($data['name'] === $identifier) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a new group
     *
     * @param string $name Group name
     * @return GroupInfo Created group
     * @throws GroupExistsException
     */
    public function create(string $name): GroupInfo
    {
        // Check if name already exists
        foreach ($this->groups as $data) {
            if ($data['name'] === $name) {
                throw new GroupExistsException("Group '$name' already exists");
            }
        }

        $id = 'group_' . $this->nextId++;
        $this->groups[$id] = [
            'name' => $name,
            'members' => [],
        ];

        return new GroupInfo(
            id: $id,
            name: $name,
            members: []
        );
    }

    /**
     * Delete a group
     *
     * @param string $identifier Group ID or name
     * @throws GroupNotFoundException
     */
    public function delete(string $identifier): void
    {
        // Try by ID first
        if (isset($this->groups[$identifier])) {
            unset($this->groups[$identifier]);
            return;
        }

        // Try by name
        foreach ($this->groups as $id => $data) {
            if ($data['name'] === $identifier) {
                unset($this->groups[$id]);
                return;
            }
        }

        throw new GroupNotFoundException("Group '$identifier' not found");
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
     * Add a single member to a group (idempotent)
     *
     * @param string $identifier Group ID or name
     * @param string $username Username to add
     * @throws GroupNotFoundException
     */
    public function addMember(string $identifier, string $username): void
    {
        $group = $this->get($identifier); // Throws if not found
        $id = $this->resolveId($identifier);

        if (!in_array($username, $this->groups[$id]['members'])) {
            $this->groups[$id]['members'][] = $username;
        }
    }

    /**
     * Add multiple members to a group (idempotent)
     *
     * @param string $identifier Group ID or name
     * @param array $usernames Usernames to add
     * @throws GroupNotFoundException
     */
    public function addMembers(string $identifier, array $usernames): void
    {
        foreach ($usernames as $username) {
            $this->addMember($identifier, $username);
        }
    }

    /**
     * Remove a single member from a group (idempotent)
     *
     * @param string $identifier Group ID or name
     * @param string $username Username to remove
     * @throws GroupNotFoundException
     */
    public function removeMember(string $identifier, string $username): void
    {
        $group = $this->get($identifier); // Throws if not found
        $id = $this->resolveId($identifier);

        $this->groups[$id]['members'] = array_values(
            array_filter(
                $this->groups[$id]['members'],
                fn($member) => $member !== $username
            )
        );
    }

    /**
     * Remove multiple members from a group (idempotent)
     *
     * @param string $identifier Group ID or name
     * @param array $usernames Usernames to remove
     * @throws GroupNotFoundException
     */
    public function removeMembers(string $identifier, array $usernames): void
    {
        foreach ($usernames as $username) {
            $this->removeMember($identifier, $username);
        }
    }

    /**
     * Replace all members of a group
     *
     * @param string $identifier Group ID or name
     * @param array $usernames New member list
     * @throws GroupNotFoundException
     */
    public function setMembers(string $identifier, array $usernames): void
    {
        $group = $this->get($identifier); // Throws if not found
        $id = $this->resolveId($identifier);

        $this->groups[$id]['members'] = array_values(array_unique($usernames));
    }

    /**
     * Check if backend is read-only
     *
     * @return bool Always false (mock supports writes)
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Resolve identifier to internal ID
     *
     * @param string $identifier Group ID or name
     * @return string Internal group ID
     * @throws GroupNotFoundException
     */
    private function resolveId(string $identifier): string
    {
        if (isset($this->groups[$identifier])) {
            return $identifier;
        }

        foreach ($this->groups as $id => $data) {
            if ($data['name'] === $identifier) {
                return $id;
            }
        }

        throw new GroupNotFoundException("Group '$identifier' not found");
    }
}
