<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Config\Driver;

use InvalidArgumentException;

/**
 * Registry for configuration drivers.
 *
 * Central repository where drivers register themselves and can be
 * looked up by type and name.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class DriverRepository
{
    /**
     * @var array<string, array<string, DriverInterface>> Drivers indexed by type and name
     */
    private array $drivers = [];

    /**
     * Register a driver.
     *
     * @param DriverInterface $driver The driver to register
     */
    public function register(DriverInterface $driver): void
    {
        $type = $driver->getType();
        $name = $driver->getName();

        if (!isset($this->drivers[$type])) {
            $this->drivers[$type] = [];
        }

        $this->drivers[$type][$name] = $driver;
    }

    /**
     * Get a specific driver.
     *
     * @param string $type Driver type (e.g., 'sql', 'nosql')
     * @param string $name Driver name (e.g., 'mysql', 'pgsql')
     *
     * @return DriverInterface The driver instance
     *
     * @throws InvalidArgumentException If driver not found
     */
    public function get(string $type, string $name): DriverInterface
    {
        if (!isset($this->drivers[$type][$name])) {
            throw new InvalidArgumentException(
                "Driver not found: {$type}/{$name}"
            );
        }

        return $this->drivers[$type][$name];
    }

    /**
     * Get all drivers of a specific type.
     *
     * @param string $type Driver type (e.g., 'sql', 'nosql')
     *
     * @return array<string, DriverInterface> Map of driver name => driver instance
     */
    public function getByType(string $type): array
    {
        return $this->drivers[$type] ?? [];
    }

    /**
     * Get all registered types.
     *
     * @return array<string> List of driver types
     */
    public function getTypes(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Check if a driver exists.
     *
     * @param string $type Driver type
     * @param string $name Driver name
     */
    public function has(string $type, string $name): bool
    {
        return isset($this->drivers[$type][$name]);
    }

    /**
     * Get count of registered drivers by type.
     *
     * @param string $type Driver type
     */
    public function count(string $type): int
    {
        return count($this->drivers[$type] ?? []);
    }
}
