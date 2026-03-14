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

namespace Horde\Core\Auth;

use Horde_Auth_Base;
use Horde_Auth_Exception;

/**
 * Modern wrapper for Horde_Auth_Base with proper dependency injection
 *
 * Provides capability detection, consistent API, and eliminates
 * reliance on global state in modern controllers.
 *
 * Wraps any Horde_Auth_Base implementation (Horde_Core_Auth_Application,
 * Horde_Auth_Sql, Horde_Auth_Ldap, etc.)
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class AuthService
{
    /**
     * Constructor
     *
     * @param Horde_Auth_Base $auth Legacy auth backend instance
     */
    public function __construct(
        private Horde_Auth_Base $auth
    ) {}

    /**
     * Check if backend supports user listing
     *
     * Tests capability by attempting to call listUsers() and catching
     * the "Unsupported" exception thrown by base implementation.
     *
     * @return bool True if backend supports listing
     */
    public function supportsListing(): bool
    {
        try {
            $this->auth->listUsers();
            return true;
        } catch (Horde_Auth_Exception $e) {
            if (str_contains($e->getMessage(), 'Unsupported')) {
                return false;
            }
            // Re-throw unexpected errors (e.g., connection failures)
            throw $e;
        }
    }

    /**
     * List all users
     *
     * Returns array of usernames from auth backend.
     *
     * @param bool $sort Sort the users alphabetically
     * @return string[] Array of usernames
     * @throws AuthNotSupportedException If backend doesn't support listing
     * @throws Horde_Auth_Exception On backend errors
     */
    public function listUsers(bool $sort = false): array
    {
        try {
            return $this->auth->listUsers($sort);
        } catch (Horde_Auth_Exception $e) {
            if (str_contains($e->getMessage(), 'Unsupported')) {
                throw new AuthNotSupportedException(
                    'Auth backend does not support user listing'
                );
            }
            throw $e;
        }
    }

    /**
     * Check if user exists
     *
     * @param string $username Username to check
     * @return bool True if user exists
     * @throws Horde_Auth_Exception On backend errors
     */
    public function exists(string $username): bool
    {
        return $this->auth->exists($username);
    }

    /**
     * Update user password
     *
     * @param string $username Username to update
     * @param string $newPassword New password
     * @return void
     * @throws Horde_Auth_Exception On backend errors or if user doesn't exist
     */
    public function updatePassword(string $username, string $newPassword): void
    {
        $this->auth->updateUser($username, $username, [
            'password' => $newPassword,
        ]);
    }

    /**
     * Create new user
     *
     * @param string $username Username for new user
     * @param array $credentials User credentials and attributes
     *                          Required: 'password'
     *                          Optional: 'email', 'full_name', etc.
     * @return void
     * @throws Horde_Auth_Exception On backend errors or if user exists
     */
    public function createUser(string $username, array $credentials): void
    {
        $this->auth->addUser($username, $credentials);
    }

    /**
     * Delete user
     *
     * @param string $username Username to delete
     * @return void
     * @throws Horde_Auth_Exception On backend errors
     */
    public function deleteUser(string $username): void
    {
        $this->auth->removeUser($username);
    }

    /**
     * Authenticate user credentials
     *
     * @param string $username Username to authenticate
     * @param string $password Password to verify
     * @return bool True if credentials valid
     * @throws Horde_Auth_Exception On backend errors
     */
    public function authenticate(string $username, string $password): bool
    {
        return $this->auth->authenticate($username, [
            'password' => $password,
        ]);
    }

    /**
     * Search users by substring
     *
     * @param string $search Search term
     * @return string[] Array of matching usernames
     * @throws AuthNotSupportedException If backend doesn't support searching
     * @throws Horde_Auth_Exception On backend errors
     */
    public function searchUsers(string $search): array
    {
        try {
            return $this->auth->searchUsers($search);
        } catch (Horde_Auth_Exception $e) {
            if (str_contains($e->getMessage(), 'Unsupported')) {
                throw new AuthNotSupportedException(
                    'Auth backend does not support user searching'
                );
            }
            throw $e;
        }
    }

    /**
     * Get underlying auth backend instance
     *
     * Provides escape hatch for operations not yet wrapped by this service.
     * Use sparingly - prefer adding methods to AuthService instead.
     *
     * @return Horde_Auth_Base
     */
    public function getBackend(): Horde_Auth_Base
    {
        return $this->auth;
    }
}
