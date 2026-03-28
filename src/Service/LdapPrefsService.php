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

/**
 * LDAP-based preferences service implementation
 *
 * Stores user preferences as LDAP attributes using hordePerson objectClass.
 * Preference attributes are named: hordePref<Scope><Key>
 * Example: hordePrefHordeTheme stores the 'theme' preference in 'horde' scope.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class LdapPrefsService implements PrefsService
{
    /**
     * Constructor
     *
     * @param HordeLdapService $ldapService LDAP service for connections
     * @param string $basedn Base DN for user searches
     */
    public function __construct(
        private HordeLdapService $ldapService,
        private string $basedn
    ) {
    }

    /**
     * Get preference value
     *
     * @param string $uid User ID
     * @param string $scope Preference scope (application name)
     * @param string $key Preference key
     * @return string|null Preference value or null if not found
     * @throws \RuntimeException If LDAP error occurs
     */
    public function getValue(string $uid, string $scope, string $key): ?string
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $userDN = $this->findUserDN($ldap, $uid);

            if (!$userDN) {
                return null;
            }

            // Search for hordePerson entry
            $filter = Horde_Ldap_Filter::create('objectClass', 'equals', 'hordePerson');
            $attrName = $this->buildAttributeName($scope, $key);

            $search = $ldap->search($userDN, $filter, [
                'attributes' => [$attrName],
                'scope' => 'sub',
            ]);

            if ($search->count() === 0) {
                // No hordePerson entry, check user entry directly
                try {
                    $entry = $ldap->getEntry($userDN, ['attributes' => [$attrName]]);
                    return $entry->getValue($attrName, 'single');
                } catch (Horde_Ldap_Exception $e) {
                    return null;
                }
            }

            $entry = $search->shiftEntry();
            return $entry->getValue($attrName, 'single');
        } catch (Horde_Ldap_Exception $e) {
            throw new \RuntimeException("Failed to get LDAP preference '$scope:$key' for user '$uid': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Set preference value
     *
     * @param string $uid User ID
     * @param string $scope Preference scope
     * @param string $key Preference key
     * @param mixed $value Preference value
     * @throws \RuntimeException If LDAP error occurs
     */
    public function setValue(string $uid, string $scope, string $key, $value): void
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $userDN = $this->findUserDN($ldap, $uid);

            if (!$userDN) {
                throw new \RuntimeException("User '$uid' not found in LDAP");
            }

            // Convert value to string for LDAP storage
            $stringValue = is_string($value) ? $value : serialize($value);

            // Try to find existing hordePerson entry
            $filter = Horde_Ldap_Filter::create('objectClass', 'equals', 'hordePerson');
            $search = $ldap->search($userDN, $filter, ['scope' => 'sub']);

            $attrName = $this->buildAttributeName($scope, $key);

            if ($search->count() === 0) {
                // No hordePerson entry, modify user entry directly
                $entry = $ldap->getEntry($userDN);

                // Add hordePerson objectClass if not present
                $objectClasses = $entry->getValue('objectClass');
                if (!in_array('hordePerson', $objectClasses)) {
                    $objectClasses[] = 'hordePerson';
                    $entry->replace(['objectClass' => $objectClasses], false);
                }

                // Set preference attribute
                $entry->replace([$attrName => $stringValue], false);
                $entry->update();
            } else {
                // Update existing hordePerson entry
                $entry = $search->shiftEntry();
                $entry->replace([$attrName => $stringValue]);
                $entry->update();
            }
        } catch (Horde_Ldap_Exception $e) {
            throw new \RuntimeException("Failed to set LDAP preference '$scope:$key' for user '$uid': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete preference value
     *
     * @param string $uid User ID
     * @param string $scope Preference scope
     * @param string $key Preference key
     * @throws \RuntimeException If LDAP error occurs
     */
    public function deleteValue(string $uid, string $scope, string $key): void
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $userDN = $this->findUserDN($ldap, $uid);

            if (!$userDN) {
                return; // User not found, nothing to delete
            }

            // Try to find hordePerson entry
            $filter = Horde_Ldap_Filter::create('objectClass', 'equals', 'hordePerson');
            $search = $ldap->search($userDN, $filter, ['scope' => 'sub']);

            $attrName = $this->buildAttributeName($scope, $key);

            if ($search->count() > 0) {
                $entry = $search->shiftEntry();
                $entry->delete([$attrName => []]);
                $entry->update();
            } else {
                // Try user entry directly
                try {
                    $entry = $ldap->getEntry($userDN);
                    $entry->delete([$attrName => []]);
                    $entry->update();
                } catch (Horde_Ldap_Exception $e) {
                    // Attribute doesn't exist, that's fine
                }
            }
        } catch (Horde_Ldap_Exception $e) {
            throw new \RuntimeException("Failed to delete LDAP preference '$scope:$key' for user '$uid': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get all preferences in scope
     *
     * @param string $uid User ID
     * @param string $scope Preference scope
     * @return array Associative array of key => value
     * @throws \RuntimeException If LDAP error occurs
     */
    public function getAllInScope(string $uid, string $scope): array
    {
        try {
            $ldap = $this->ldapService->getAdapter();
            $userDN = $this->findUserDN($ldap, $uid);

            if (!$userDN) {
                return [];
            }

            // Search for hordePerson entry with all attributes
            $filter = Horde_Ldap_Filter::create('objectClass', 'equals', 'hordePerson');
            $search = $ldap->search($userDN, $filter, ['scope' => 'sub']);

            $prefs = [];
            $prefix = 'hordePref' . $scope;
            $prefixLen = strlen($prefix);

            if ($search->count() > 0) {
                $entry = $search->shiftEntry();
            } else {
                // Try user entry directly
                try {
                    $entry = $ldap->getEntry($userDN);
                } catch (Horde_Ldap_Exception $e) {
                    return [];
                }
            }

            // Extract preferences matching the scope prefix
            foreach ($entry->getValues() as $attrName => $values) {
                if (strpos($attrName, $prefix) === 0) {
                    $key = substr($attrName, $prefixLen);
                    // Lowercase first character for consistency
                    $key = lcfirst($key);
                    $prefs[$key] = is_array($values) ? $values[0] : $values;
                }
            }

            return $prefs;
        } catch (Horde_Ldap_Exception $e) {
            throw new \RuntimeException("Failed to get LDAP preferences in scope '$scope' for user '$uid': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if preference exists
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @return bool True if preference exists
     */
    public function exists(string $uid, string $scope, string $key): bool
    {
        try {
            $value = $this->getValue($uid, $scope, $key);
            return $value !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Find user DN by username
     *
     * @param Horde_Ldap $ldap LDAP connection
     * @param string $uid User ID
     * @return string|null User DN or null if not found
     */
    private function findUserDN(Horde_Ldap $ldap, string $uid): ?string
    {
        try {
            return $ldap->findUserDN($uid);
        } catch (Horde_Ldap_Exception $e) {
            // User not found
            return null;
        }
    }

    /**
     * Build LDAP attribute name for preference
     *
     * Format: hordePref<Scope><Key>
     * Example: hordePrefHordeTheme for scope='horde', key='theme'
     *
     * @param string $scope Preference scope
     * @param string $key Preference key
     * @return string LDAP attribute name
     */
    private function buildAttributeName(string $scope, string $key): string
    {
        // Capitalize first letter of scope and key for camelCase
        $scope = ucfirst(strtolower($scope));
        $key = ucfirst($key);

        return "hordePref{$scope}{$key}";
    }
}
