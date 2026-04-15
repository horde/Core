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

use Horde\Core\Factory\PrefsServiceFactory;
use Horde\Injector\Attribute\Factory;

/**
 *
 * Provides access to user preferences storage backends.
 * Prefs are scoped by user AND application.
 *
 * For identities/user management, we focus on storage/retrieval only.
 * Schema, defaults, locking, and enforced prefs are out of scope.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[Factory(factory: PrefsServiceFactory::class, method: 'create')]
interface PrefsService
{
    /**
     * Get preference value
     *
     * @param string $uid User ID
     * @param string $scope App name ('horde', 'imp', etc.)
     * @param string $key Preference key
     * @return mixed|null Preference value or null if not found
     */
    public function getValue(string $uid, string $scope, string $key);

    /**
     * Set preference value
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @param mixed $value Preference value (will be serialized if needed)
     * @return void
     */
    public function setValue(string $uid, string $scope, string $key, $value): void;

    /**
     * Delete preference
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @return void
     */
    public function deleteValue(string $uid, string $scope, string $key): void;

    /**
     * Get all preferences for user in scope
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @return array Associative array of key => value
     */
    public function getAllInScope(string $uid, string $scope): array;

    /**
     * Check if preference exists
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @return bool True if preference exists
     */
    public function exists(string $uid, string $scope, string $key): bool;
}
