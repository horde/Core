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
 * Modern wrapper for identity management
 *
 * Wraps preference-based identity storage with clean API for REST services.
 * Identities are tied to a user UID but don't require auth backend user.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class IdentityService
{
    /**
     * Constructor
     *
     * @param PrefsService $prefsService Preferences service
     */
    public function __construct(
        private PrefsService $prefsService
    ) {}

    /**
     * Get all identities for user
     *
     * @param string $uid User ID
     * @param string $scope App scope (default: 'horde')
     * @return array Array of identities (each with id, fullname, from_addr, etc.)
     */
    public function getAll(string $uid, string $scope = 'horde'): array
    {
        $identities = $this->prefsService->getValue($uid, $scope, 'identities');
        if (!$identities) {
            return [];
        }
        $unserialized = @unserialize($identities);
        return is_array($unserialized) ? $unserialized : [];
    }

    /**
     * Get single identity by index
     *
     * @param string $uid User ID
     * @param int $index Identity index (0-based)
     * @param string $scope App scope
     * @return array|null Identity or null if not found
     */
    public function get(string $uid, int $index, string $scope = 'horde'): ?array
    {
        $identities = $this->getAll($uid, $scope);
        return $identities[$index] ?? null;
    }

    /**
     * Add new identity
     *
     * @param string $uid User ID
     * @param array $identity Identity data (id, fullname, from_addr, etc.)
     * @param string $scope App scope
     * @return int Index of created identity
     */
    public function add(string $uid, array $identity, string $scope = 'horde'): int
    {
        $identities = $this->getAll($uid, $scope);
        $identities[] = $identity;
        $this->save($uid, $identities, $scope);
        return count($identities) - 1;
    }

    /**
     * Update existing identity
     *
     * @param string $uid User ID
     * @param int $index Identity index
     * @param array $identity New identity data
     * @param string $scope App scope
     * @throws IdentityNotFoundException
     */
    public function update(string $uid, int $index, array $identity, string $scope = 'horde'): void
    {
        $identities = $this->getAll($uid, $scope);
        if (!isset($identities[$index])) {
            throw new IdentityNotFoundException("Identity $index not found for user $uid");
        }
        $identities[$index] = $identity;
        $this->save($uid, $identities, $scope);
    }

    /**
     * Delete identity
     *
     * @param string $uid User ID
     * @param int $index Identity index
     * @param string $scope App scope
     * @throws IdentityNotFoundException
     */
    public function delete(string $uid, int $index, string $scope = 'horde'): void
    {
        $identities = $this->getAll($uid, $scope);
        if (!isset($identities[$index])) {
            throw new IdentityNotFoundException("Identity $index not found for user $uid");
        }
        array_splice($identities, $index, 1);
        $this->save($uid, $identities, $scope);

        // Update default if needed
        $default = $this->getDefault($uid, $scope);
        if ($default >= count($identities)) {
            $this->setDefault($uid, 0, $scope);
        }
    }

    /**
     * Get default identity index
     *
     * @param string $uid User ID
     * @param string $scope App scope
     * @return int Default identity index (0-based)
     */
    public function getDefault(string $uid, string $scope = 'horde'): int
    {
        $default = $this->prefsService->getValue($uid, $scope, 'default_identity');
        return $default ? (int) $default : 0;
    }

    /**
     * Set default identity
     *
     * @param string $uid User ID
     * @param int $index Identity index
     * @param string $scope App scope
     * @throws IdentityNotFoundException
     */
    public function setDefault(string $uid, int $index, string $scope = 'horde'): void
    {
        $identities = $this->getAll($uid, $scope);
        if (!isset($identities[$index])) {
            throw new IdentityNotFoundException("Identity $index not found for user $uid");
        }
        $this->prefsService->setValue($uid, $scope, 'default_identity', (string) $index);
    }

    /**
     * Save identities to backend
     *
     * @param string $uid User ID
     * @param array $identities Array of identity data
     * @param string $scope App scope
     */
    private function save(string $uid, array $identities, string $scope): void
    {
        $this->prefsService->setValue($uid, $scope, 'identities', serialize($identities));
    }
}
