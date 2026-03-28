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

use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * Interface for configuration drivers.
 *
 * Each driver (SQL, NoSQL, LDAP, VFS, etc.) implements this interface
 * to provide metadata about its configuration requirements.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
interface DriverInterface
{
    /**
     * Get the driver's unique identifier.
     *
     * @return string Driver name (e.g., 'mysql', 'pgsql', 'mongodb')
     */
    public function getName(): string;

    /**
     * Get human-readable description.
     *
     * @return string Driver description (e.g., 'MySQL / PDO')
     */
    public function getDescription(): string;

    /**
     * Get the driver type category.
     *
     * @return string Type (e.g., 'sql', 'nosql', 'ldap', 'vfs')
     */
    public function getType(): string;

    /**
     * Get configuration fields metadata.
     *
     * @return array<PropertyMetadata> List of field metadata objects
     */
    public function getFields(): array;

    /**
     * Validate configuration data.
     *
     * @param array<string, mixed> $config Configuration data to validate
     *
     * @return ValidationResult Validation result
     */
    public function validate(array $config): ValidationResult;
}
