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

namespace Horde\Core\Config;

use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * Configuration state with metadata support.
 *
 * Extends State to provide metadata queries and validation
 * for configuration values.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ConfigStateWithMetadata extends State
{
    public function __construct(
        ?array $conf = null,
        private readonly ?ConfigMetadataProvider $metadataProvider = null,
    ) {
        parent::__construct($conf);
    }

    /**
     * Get metadata for a configuration key.
     *
     * @param string $key Configuration key (supports dot notation)
     * @param string $type Driver type (e.g., 'sql')
     * @param string $driver Driver name (e.g., 'mysql')
     *
     * @return PropertyMetadata|null Metadata or null if not found
     */
    public function getMetadata(string $key, string $type, string $driver): ?PropertyMetadata
    {
        if ($this->metadataProvider === null) {
            return null;
        }

        try {
            $driverInstance = $this->metadataProvider
                ->validateConfig($type, $driver, [])
                ->isValid(); // Just to verify driver exists

            $schema = $this->metadataProvider->getDriverSchema($type, $driver);

            // Extract field name from key (e.g., 'sql.username' -> 'username')
            $fieldName = substr($key, strrpos($key, '.') + 1);

            foreach ($schema['fields'] as $field) {
                if ($field['name'] === $fieldName) {
                    return $this->arrayToMetadata($field);
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Validate configuration section against driver schema.
     *
     * @param string $type Driver type (e.g., 'sql')
     * @param string $driver Driver name (e.g., 'mysql')
     *
     * @return ValidationResult Validation result
     */
    public function validate(string $type, string $driver): ValidationResult
    {
        if ($this->metadataProvider === null) {
            return new ValidationResult(['Metadata provider not available']);
        }

        // Get the configuration section for this type
        $config = $this->get($type, []);
        if (!is_array($config)) {
            return new ValidationResult(["Configuration for '{$type}' is not an array"]);
        }

        return $this->metadataProvider->validateConfig($type, $driver, $config);
    }

    /**
     * Convert array representation to PropertyMetadata.
     *
     * @param array<string, mixed> $field Field data
     */
    private function arrayToMetadata(array $field): PropertyMetadata
    {
        // This is a simplified conversion - real implementation would
        // need to properly reconstruct the full PropertyMetadata object
        // including FieldType enum, ConditionalFields, etc.

        // For now, return a basic metadata object
        // A full implementation would require access to the actual driver instance
        return new PropertyMetadata(
            name: $field['name'],
            type: \Horde\Core\Config\Metadata\FieldType::from($field['type']),
            description: $field['description'] ?? '',
            required: $field['required'] ?? false,
            default: $field['default'] ?? null,
        );
    }
}
