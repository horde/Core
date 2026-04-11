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
 * Group information domain object
 *
 * Immutable value object representing a group with its members.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class GroupInfo
{
    /**
     * Constructor
     *
     * @param string $id Group identifier (group_uid for SQL, DN for LDAP)
     * @param string $name Group name (group_name for SQL, cn for LDAP)
     * @param array $members List of usernames who are members
     * @param array $extra Backend-specific extra fields (reserved for future use)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $members,
        public readonly array $extra = []
    ) {}

    /**
     * Create from backend data array
     *
     * Factory method to create GroupInfo from Horde_Group_Base::getData() result.
     *
     * @param array $data Backend data array with keys: group_uid, group_name, etc.
     * @param array $members List of usernames
     * @return self
     */
    public static function fromBackendData(array $data, array $members): self
    {
        return new self(
            id: (string) $data['group_uid'],
            name: $data['group_name'],
            members: $members,
            extra: []
        );
    }

    /**
     * Convert to array representation
     *
     * @return array Associative array with id, name, members
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'members' => $this->members,
        ];
    }
}
