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

use Horde\Core\Service\HordeLdapService;
use Horde_Ldap;
use Horde_Ldap_Filter;
use Horde_Ldap_Exception;
use Horde_Ldap_Entry;
use RuntimeException;

/**
 * LDAP-based group service implementation
 *
 * Manages groups stored in LDAP directory with support for multiple
 * objectClass schemas (posixGroup, groupOfNames, groupOfUniqueNames).
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class LdapGroupService implements GroupService
{
    /**
     * Constructor
     *
     * @param HordeLdapService $ldapService LDAP service for connections
     * @param string $basedn Base DN for group searches
     * @param string $gidAttr Attribute for group ID (default: 'cn')
     * @param string $memberAttr Attribute for member list (default: 'memberUid')
     * @param array $search Search filter config, same shape as legacy
     *                       Horde_Group_Ldap's 'search' param: either
     *                       ['objectclass' => 'name'|['name',...]] or
     *                       ['filter' => '(raw ldap filter)']
     * @param array $newGroupObjectClass Object classes for new groups (default: ['posixGroup'])
     */
    public function __construct(
        private HordeLdapService $ldapService,
        private string $basedn,
        private string $gidAttr = 'cn',
        private string $memberAttr = 'memberUid',
        private array $search = ['objectclass' => ['posixGroup']],
        private array $newGroupObjectClass = ['posixGroup']
    ) {}

    /**
     * List all groups
     *
     * @param int $page Page number (1-based)
     * @param int $perPage Number of groups per page
     * @return GroupListResult List of all groups
     * @throws RuntimeException If LDAP search fails
     */
    public function listAll(int $page = 1, int $perPage = 50): GroupListResult
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $filter = $this->buildFilter();

            $search = $ldap->search($this->basedn, $filter, [
                'attributes' => [$this->gidAttr, $this->memberAttr, 'mail', 'description'],
            ]);

            $allGroups = [];
            foreach ($search as $entry) {
                // getValue() returns null when the attribute is missing;
                // skip entries without a group ID since GroupInfo requires
                // a non-null string id.
                $gid = $entry->getValue($this->gidAttr, 'single');
                if ($gid === null) {
                    continue;
                }
                $members = $entry->getValue($this->memberAttr);
                $mail = $entry->getValue('mail', 'single');

                $extra = [];
                if ($mail) {
                    $extra['email'] = $mail;
                }

                $allGroups[] = new GroupInfo(
                    id: $gid,
                    name: $gid,
                    members: is_array($members) ? $members : [],
                    extra: $extra
                );
            }

            // Apply pagination
            $total = count($allGroups);
            $offset = ($page - 1) * $perPage;
            $groups = array_slice($allGroups, $offset, $perPage);

            $hasNext = ($offset + $perPage) < $total;
            $hasPrev = $page > 1;

            return new GroupListResult($groups, $total, $page, $perPage, $hasNext, $hasPrev);
        } catch (Horde_Ldap_Exception $e) {
            throw new RuntimeException('Failed to list LDAP groups: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get group by ID
     *
     * @param string $id Group ID
     * @return GroupInfo Group information
     * @throws RuntimeException If group not found or LDAP error
     */
    public function get(string $id): GroupInfo
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $dn = $this->buildDN($id);

            $entry = $ldap->getEntry($dn, [$this->gidAttr, $this->memberAttr, 'mail', 'description']);

            // Horde_Ldap_Entry::getValue() throws when an attribute is
            // genuinely absent from the entry (not just empty) - mail and
            // group membership are both optional on a given LDAP entry.
            // Legacy Horde_Group_Ldap guards every read with exists() for
            // the same reason.
            $members = $entry->exists($this->memberAttr)
                ? $entry->getValue($this->memberAttr, 'all')
                : [];
            $mail = $entry->exists('mail') ? $entry->getValue('mail', 'single') : null;

            $extra = [];
            if ($mail) {
                $extra['email'] = $mail;
            }

            return new GroupInfo(
                id: $id,
                name: $id,
                members: is_array($members) ? $members : [],
                extra: $extra
            );
        } catch (Horde_Ldap_Exception $e) {
            throw new RuntimeException("Failed to get LDAP group '$id': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create new group
     *
     * @param string $name Group name (must be unique)
     * @return GroupInfo Created group information
     * @throws RuntimeException If group creation fails
     */
    public function create(string $name): GroupInfo
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $dn = $this->buildDN($name);

            $attributes = [
                'objectClass' => $this->newGroupObjectClass,
                $this->gidAttr => $name,
            ];

            // posixGroup requires gidNumber
            if (in_array('posixGroup', $this->newGroupObjectClass)) {
                $attributes['gidNumber'] = $this->getNextGidNumber();
            }

            $entry = Horde_Ldap_Entry::createFresh($dn, $attributes);
            $ldap->add($entry);

            return new GroupInfo(
                id: $name,
                name: $name,
                members: [],
                extra: []
            );
        } catch (Horde_Ldap_Exception $e) {
            throw new RuntimeException("Failed to create LDAP group '$name': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete group
     *
     * @param string $id Group ID
     * @throws RuntimeException If group deletion fails
     */
    public function delete(string $id): void
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $dn = $this->buildDN($id);

            $ldap->delete($dn);
        } catch (Horde_Ldap_Exception $e) {
            throw new RuntimeException("Failed to delete LDAP group '$id': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if group exists
     *
     * @param string $id Group ID
     * @return bool True if group exists
     */
    public function exists(string $id): bool
    {
        try {
            $this->get($id);
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Get group members
     *
     * @param string $groupId Group ID
     * @return array List of user IDs
     * @throws RuntimeException If operation fails
     */
    public function getMembers(string $groupId): array
    {
        $group = $this->get($groupId);
        return $group->members;
    }

    /**
     * Add member to group
     *
     * @param string $groupId Group ID
     * @param string $userId User ID
     * @throws RuntimeException If operation fails
     */
    public function addMember(string $groupId, string $userId): void
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $dn = $this->buildDN($groupId);

            $entry = $ldap->getEntry($dn);
            $members = $entry->getValue($this->memberAttr);
            $members = is_array($members) ? $members : [];

            if (!in_array($userId, $members)) {
                $members[] = $userId;
                $entry->replace([$this->memberAttr => $members]);
                $entry->update();
            }
        } catch (Horde_Ldap_Exception $e) {
            throw new RuntimeException("Failed to add member '$userId' to LDAP group '$groupId': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Add multiple members to group
     *
     * @param string $groupId Group ID
     * @param array $userIds Array of user IDs
     * @throws RuntimeException If operation fails
     */
    public function addMembers(string $groupId, array $userIds): void
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $dn = $this->buildDN($groupId);

            $entry = $ldap->getEntry($dn);
            $members = $entry->getValue($this->memberAttr);
            $members = is_array($members) ? $members : [];

            $newMembers = array_unique(array_merge($members, $userIds));

            if (count($newMembers) !== count($members)) {
                $entry->replace([$this->memberAttr => $newMembers]);
                $entry->update();
            }
        } catch (Horde_Ldap_Exception $e) {
            throw new RuntimeException("Failed to add members to LDAP group '$groupId': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Remove member from group
     *
     * @param string $groupId Group ID
     * @param string $userId User ID
     * @throws RuntimeException If operation fails
     */
    public function removeMember(string $groupId, string $userId): void
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $dn = $this->buildDN($groupId);

            $entry = $ldap->getEntry($dn);
            $members = $entry->getValue($this->memberAttr);
            $members = is_array($members) ? $members : [];

            $members = array_values(array_filter($members, fn($m) => $m !== $userId));

            $entry->replace([$this->memberAttr => $members]);
            $entry->update();
        } catch (Horde_Ldap_Exception $e) {
            throw new RuntimeException("Failed to remove member '$userId' from LDAP group '$groupId': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Remove multiple members from group
     *
     * @param string $groupId Group ID
     * @param array $userIds Array of user IDs
     * @throws RuntimeException If operation fails
     */
    public function removeMembers(string $groupId, array $userIds): void
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $dn = $this->buildDN($groupId);

            $entry = $ldap->getEntry($dn);
            $members = $entry->getValue($this->memberAttr);
            $members = is_array($members) ? $members : [];

            $members = array_values(array_diff($members, $userIds));

            $entry->replace([$this->memberAttr => $members]);
            $entry->update();
        } catch (Horde_Ldap_Exception $e) {
            throw new RuntimeException("Failed to remove members from LDAP group '$groupId': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Replace all members of a group
     *
     * @param string $groupId Group ID
     * @param array $userIds Array of user IDs (new member list)
     * @throws RuntimeException If operation fails
     */
    public function setMembers(string $groupId, array $userIds): void
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $dn = $this->buildDN($groupId);

            $entry = $ldap->getEntry($dn);
            $entry->replace([$this->memberAttr => $userIds]);
            $entry->update();
        } catch (Horde_Ldap_Exception $e) {
            throw new RuntimeException("Failed to set members for LDAP group '$groupId': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if backend is read-only
     *
     * @return bool False - LDAP backend supports write operations
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Build LDAP filter for group searches
     *
     * @return Horde_Ldap_Filter LDAP filter
     */
    private function buildFilter(): Horde_Ldap_Filter
    {
        return Horde_Ldap_Filter::build($this->search);
    }

    /**
     * Build DN for group
     *
     * @param string $groupId Group ID
     * @return string Full DN
     */
    private function buildDN(string $groupId): string
    {
        return "{$this->gidAttr}={$groupId},{$this->basedn}";
    }

    /**
     * Get next available GID number for posixGroup
     *
     * @return int Next GID number
     */
    private function getNextGidNumber(): int
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $filter = $this->buildFilter();

            $search = $ldap->search($this->basedn, $filter, [
                'attributes' => ['gidNumber'],
            ]);

            $maxGid = 10000; // Start from 10000 for safety
            foreach ($search as $entry) {
                $gid = (int) ($entry->getValue('gidNumber', 'single') ?? 0);
                if ($gid > $maxGid) {
                    $maxGid = $gid;
                }
            }

            return $maxGid + 1;
        } catch (Horde_Ldap_Exception $e) {
            // Fallback to random high number
            return 10000 + random_int(1, 10000);
        }
    }
}
