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
 * Registry configuration state
 *
 * Immutable state object holding application registry loaded from registry.php
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class RegistryState
{
    /**
     * Constructor
     *
     * @param array $applications Merged application definitions
     */
    public function __construct(
        private array $applications
    ) {}

    /**
     * Get specific application definition
     *
     * @param string $app App name ('horde', 'turba', 'imp', etc.)
     * @return array|null App definition or null if not found
     */
    public function getApplication(string $app): ?array
    {
        return $this->applications[$app] ?? null;
    }

    /**
     * List all application names
     *
     * @return array List of app names
     */
    public function listApplications(): array
    {
        return array_keys($this->applications);
    }

    /**
     * Check if application exists
     *
     * @param string $app App name
     * @return bool True if app exists in registry
     */
    public function hasApplication(string $app): bool
    {
        return isset($this->applications[$app]);
    }

    /**
     * Get all applications as array (for legacy compatibility)
     *
     * @return array Raw applications array
     */
    public function toArray(): array
    {
        return $this->applications;
    }
}
