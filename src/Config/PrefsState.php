<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Config;

/**
 * Preferences configuration state
 *
 * Immutable state object holding preference definitions loaded from prefs.php
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class PrefsState
{
    /**
     * Constructor
     *
     * @param array $prefs Merged $_prefs array
     * @param array $prefGroups Merged $prefGroups array
     */
    public function __construct(
        private array $prefs,
        private array $prefGroups = []
    ) {}

    /**
     * Get specific preference definition
     *
     * @param string $name Pref name ('sync_books', 'name_format', etc.)
     * @return array|null Pref definition or null if not found
     */
    public function getPref(string $name): ?array
    {
        return $this->prefs[$name] ?? null;
    }

    /**
     * List all preference names
     *
     * @return array List of pref names
     */
    public function listPrefs(): array
    {
        return array_keys($this->prefs);
    }

    /**
     * Get preference groups
     *
     * @return array Preference groups array
     */
    public function getPrefGroups(): array
    {
        return $this->prefGroups;
    }

    /**
     * Get specific preference group
     *
     * @param string $name Group name
     * @return array|null Group definition or null
     */
    public function getPrefGroup(string $name): ?array
    {
        return $this->prefGroups[$name] ?? null;
    }

    /**
     * Check if preference exists
     *
     * @param string $name Pref name
     * @return bool True if pref exists
     */
    public function hasPref(string $name): bool
    {
        return isset($this->prefs[$name]);
    }

    /**
     * Get all preference definitions
     *
     * @return array All preference definitions indexed by name
     */
    public function getAllPrefs(): array
    {
        return $this->prefs;
    }

    /**
     * Get all prefs and groups as array (for legacy compatibility)
     *
     * @return array ['_prefs' => [...], 'prefGroups' => [...]]
     */
    public function toArray(): array
    {
        return [
            '_prefs' => $this->prefs,
            'prefGroups' => $this->prefGroups,
        ];
    }
}
