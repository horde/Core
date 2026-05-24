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

use Horde\Exception\HordeRuntimeException;

/**
 * Registry configuration state
 *
 * Immutable state object holding application registry loaded from registry.php.
 * Validates that all active apps have the required URI keys after loading.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class RegistryState
{
    private const ACTIVE_STATUSES = ['active', 'notoolbar', 'hidden', 'admin'];

    private const REQUIRED_KEYS = ['webroot', 'fileroot', 'jsuri', 'themesuri'];

    /**
     * Constructor
     *
     * @param array $applications Merged application definitions
     */
    public function __construct(
        private array $applications
    ) {
        $this->validate();
    }

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

    /**
     * Validate that active apps have all required URI keys.
     *
     * @throws HordeRuntimeException If required keys are missing
     */
    private function validate(): void
    {
        $errors = [];

        foreach ($this->applications as $app => $config) {
            $status = $config['status'] ?? 'inactive';
            if (!in_array($status, self::ACTIVE_STATUSES)) {
                continue;
            }

            $missing = [];
            foreach (self::REQUIRED_KEYS as $key) {
                if (!isset($config[$key]) || $config[$key] === '') {
                    $missing[] = $key;
                }
            }

            if ($missing !== []) {
                $errors[] = $app . ': ' . implode(', ', $missing);
            }

            if ($app === 'horde' && (!isset($config['staticuri']) || $config['staticuri'] === '')) {
                $errors[] = 'horde: staticuri';
            }
        }

        if ($errors !== []) {
            throw new HordeRuntimeException(
                'Registry configuration incomplete. Missing required keys for active apps: '
                . implode('; ', $errors)
                . '. Run "composer horde:reconfigure" to generate missing values.'
            );
        }
    }
}
