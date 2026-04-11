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
 * Backend configuration state
 *
 * Immutable state object holding backend definitions loaded from backends.php
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class BackendState
{
    /**
     * Constructor
     *
     * @param array $backends Merged backend definitions
     */
    public function __construct(
        private array $backends
    ) {}

    /**
     * Get specific backend definition
     *
     * @param string $name Backend name ('hordesql', 'ldap', etc.)
     * @return array|null Backend config or null if not found
     */
    public function getBackend(string $name): ?array
    {
        return $this->backends[$name] ?? null;
    }

    /**
     * List all backends
     *
     * @param bool $includeDisabled Include disabled backends (default: false)
     * @return array Backend definitions keyed by name
     */
    public function listBackends(bool $includeDisabled = false): array
    {
        if ($includeDisabled) {
            return $this->backends;
        }

        return array_filter(
            $this->backends,
            fn($backend) => empty($backend['disabled'])
        );
    }

    /**
     * Check if backend exists
     *
     * @param string $name Backend name
     * @return bool True if backend exists (regardless of disabled status)
     */
    public function hasBackend(string $name): bool
    {
        return isset($this->backends[$name]);
    }

    /**
     * Get all backends as array (for legacy compatibility)
     *
     * @return array Raw backends array
     */
    public function toArray(): array
    {
        return $this->backends;
    }
}
