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

use Horde\Core\Config\PrefsConfigLoader;
use Horde\Core\Service\HordeLdapService;
use Horde_Ldap;
use Horde_Ldap_Filter;
use Horde_Ldap_Exception;
use Exception;
use RuntimeException;

/**
 * LDAP-based preferences service implementation
 *
 * Stores user preferences as LDAP attributes using hordePerson objectClass.
 * Preference attributes are named: hordePref<Scope><Key>
 * Example: hordePrefHordeTheme stores the 'theme' preference in 'horde' scope.
 *
 * ## Cascade order
 *
 * The five-layer config file cascade (via PrefsConfigCascadeTrait) is applied
 * on top of the LDAP user attributes:
 *
 *   1. Config defaults seed the result (all non-UI prefs with a 'value').
 *   2. Locked prefs keep their config value regardless of any LDAP attribute.
 *   3. Unlocked prefs: LDAP attribute value overwrites the config default.
 *   4. LDAP-only prefs (no config definition) are included as-is.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class LdapPrefsService implements PrefsService
{
    use PrefsConfigCascadeTrait;

    /**
     * Constructor
     *
     * @param HordeLdapService  $ldapService        LDAP service for connections
     * @param PrefsConfigLoader $prefsConfigLoader  Config cascade loader
     * @param string            $basedn             Base DN for user searches
     */
    public function __construct(
        private HordeLdapService $ldapService,
        private PrefsConfigLoader $prefsConfigLoader,
        private string $basedn
    ) {}

    /**
     * Get preference value
     *
     * Returns the effective value applying the full cascade:
     *   - Locked prefs always return the config-layer value; LDAP is never
     *     consulted.
     *   - Unlocked prefs: LDAP attribute value if present, else config default.
     *   - Config default (or null) when the user has no LDAP entry.
     *
     * @param string $uid   User ID
     * @param string $scope Preference scope (application name)
     * @param string $key   Preference key
     * @return mixed Preference value, config default, or null
     * @throws RuntimeException If a non-recoverable LDAP error occurs
     */
    public function getValue(string $uid, string $scope, string $key): mixed
    {
        // Locked prefs always use the config-layer value.
        if ($this->isLocked($uid, $scope, $key)) {
            return $this->configDefault($scope, $key);
        }

        try {
            $ldap   = $this->ldapService->getAdapter();
            $userDN = $this->findUserDN($ldap, $uid);

            if (!$userDN) {
                return $this->configDefault($scope, $key);
            }

            // Search for hordePerson entry
            $filter   = Horde_Ldap_Filter::create('objectClass', 'equals', 'hordePerson');
            $attrName = $this->buildAttributeName($scope, $key);

            $search = $ldap->search($userDN, $filter, [
                'attributes' => [$attrName],
                'scope'      => 'sub',
            ]);

            if ($search->count() === 0) {
                // No hordePerson entry. Check user entry directly
                try {
                    $entry = $ldap->getEntry($userDN, ['attributes' => [$attrName]]);
                    $value = $entry->getValue($attrName, 'single');
                } catch (Horde_Ldap_Exception $e) {
                    $value = null;
                }
            } else {
                $entry = $search->shiftEntry();
                $value = $entry->getValue($attrName, 'single');
            }

            // Fall back to config default when LDAP has no stored value
            return $value ?? $this->configDefault($scope, $key);
        } catch (Horde_Ldap_Exception $e) {
            throw new RuntimeException(
                "Failed to get LDAP preference '$scope:$key' for user '$uid': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Set preference value
     *
     * Refuses to write a preference that is locked in the config cascade.
     * Throws RuntimeException so callers
     * can rely on a consistent lock-enforcement contract regardless of backend.
     *
     * @param string $uid   User ID
     * @param string $scope Preference scope
     * @param string $key   Preference key
     * @param mixed  $value Preference value
     * @throws RuntimeException If the preference is locked in config, or on LDAP error
     */
    public function setValue(string $uid, string $scope, string $key, $value): void
    {
        $this->assertNotLocked($scope, $key);

        try {
            $ldap   = $this->ldapService->getAdapter();
            $userDN = $this->findUserDN($ldap, $uid);

            if (!$userDN) {
                throw new RuntimeException("User '$uid' not found in LDAP");
            }

            // Convert value to string for LDAP storage
            $stringValue = is_string($value) ? $value : serialize($value);

            // Try to find existing hordePerson entry
            $filter   = Horde_Ldap_Filter::create('objectClass', 'equals', 'hordePerson');
            $search   = $ldap->search($userDN, $filter, ['scope' => 'sub']);
            $attrName = $this->buildAttributeName($scope, $key);

            if ($search->count() === 0) {
                // No hordePerson entry. Modify user entry directly
                $entry = $ldap->getEntry($userDN);

                // Add hordePerson objectClass if not present.
                // getValue() may return null if the entry has no
                // objectClass attribute; default to empty array.
                $objectClasses = $entry->getValue('objectClass') ?? [];
                if (!is_array($objectClasses)) {
                    $objectClasses = [$objectClasses];
                }
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
            throw new RuntimeException(
                "Failed to set LDAP preference '$scope:$key' for user '$uid': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Delete preference value
     *
     * @param string $uid   User ID
     * @param string $scope Preference scope
     * @param string $key   Preference key
     * @throws RuntimeException If LDAP error occurs
     */
    public function deleteValue(string $uid, string $scope, string $key): void
    {
        try {
            $ldap   = $this->ldapService->getAdapter();
            $userDN = $this->findUserDN($ldap, $uid);

            if (!$userDN) {
                return; // User not found, nothing to delete
            }

            // Try to find hordePerson entry
            $filter   = Horde_Ldap_Filter::create('objectClass', 'equals', 'hordePerson');
            $search   = $ldap->search($userDN, $filter, ['scope' => 'sub']);
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
            throw new RuntimeException(
                "Failed to delete LDAP preference '$scope:$key' for user '$uid': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get all preferences in scope
     *
     * Returns the merged view applying the full cascade:
     *
     *   1. Config defaults seed the result (all storable, non-UI prefs with a
     *      'value' in config).
     *   2. Locked prefs keep their config value; any matching LDAP attribute is
     *      silently ignored.
     *   3. Unlocked prefs: LDAP attribute value overlays the config default.
     *   4. LDAP-only prefs (no config definition) are included as-is.
     *
     * If LDAP is unavailable, config defaults are returned without rethrowing.
     *
     * @param string $uid   User ID
     * @param string $scope Preference scope
     * @return array<string, mixed> Associative array of key => effective value
     */
    public function getAllInScope(string $uid, string $scope): array
    {
        // --- Step 1: seed from config defaults, record locked keys ---
        ['defaults' => $result, 'locked' => $locked] = $this->buildConfigSeed($scope);

        try {
            $ldap   = $this->ldapService->getAdapter();
            $userDN = $this->findUserDN($ldap, $uid);

            if (!$userDN) {
                return $result; // Config defaults only
            }

            // --- Step 2: find the user's hordePerson entry ---
            $filter = Horde_Ldap_Filter::create('objectClass', 'equals', 'hordePerson');
            $search = $ldap->search($userDN, $filter, ['scope' => 'sub']);

            if ($search->count() > 0) {
                $entry = $search->shiftEntry();
            } else {
                try {
                    $entry = $ldap->getEntry($userDN);
                } catch (Horde_Ldap_Exception $e) {
                    return $result; // Config defaults only
                }
            }

            // --- Step 3: overlay LDAP values, honouring locks ---
            $prefix    = 'hordePref' . $scope;
            $prefixLen = strlen($prefix);

            foreach ($entry->getValues() as $attrName => $values) {
                if (strpos($attrName, $prefix) === 0) {
                    $key = lcfirst(substr($attrName, $prefixLen));
                    if (isset($locked[$key])) {
                        // Config lock wins. Skip this LDAP attribute
                        continue;
                    }
                    $result[$key] = is_array($values) ? $values[0] : $values;
                }
            }
        } catch (Horde_Ldap_Exception $e) {
            // LDAP unavailable. Return config defaults only (no rethrow)
        }

        return $result;
    }

    /**
     * Check if preference exists
     *
     * Returns true if the preference exists in the config cascade or has a
     * stored LDAP attribute for the user. Delegates to getValue() which
     * applies the full config+LDAP cascade, including config-only defaults.
     *
     * @param string $uid   User ID
     * @param string $scope App name
     * @param string $key   Preference key
     * @return bool True if preference exists
     */
    public function exists(string $uid, string $scope, string $key): bool
    {
        try {
            $value = $this->getValue($uid, $scope, $key);
            return $value !== null;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Find user DN by username
     *
     * @param Horde_Ldap $ldap LDAP connection
     * @param string     $uid  User ID
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
     * @param string $key   Preference key
     * @return string LDAP attribute name
     */
    private function buildAttributeName(string $scope, string $key): string
    {
        // Capitalize first letter of scope and key for camelCase
        $scope = ucfirst(strtolower($scope));
        $key   = ucfirst($key);

        return "hordePref{$scope}{$key}";
    }
}
