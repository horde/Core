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

use Horde\Core\Config\Driver\DriverRepository;
use Horde\Core\Config\Metadata\ValidationResult;
use InvalidArgumentException;

/**
 * Service for querying configuration metadata.
 *
 * Provides API for:
 * - Listing available drivers
 * - Getting driver schemas
 * - Validating configurations
 * - Exporting schemas for API/CLI tools
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ConfigMetadataProvider
{
    public function __construct(
        private readonly DriverRepository $repository,
    ) {}

    /**
     * Get the driver repository.
     *
     * @return DriverRepository The driver repository
     */
    public function getRepository(): DriverRepository
    {
        return $this->repository;
    }

    /**
     * Get list of available drivers for a type.
     *
     * @param string $type Driver type (e.g., 'sql', 'nosql')
     *
     * @return array<string, string> Map of driver name => description
     */
    public function getAvailableDrivers(string $type): array
    {
        $drivers = $this->repository->getByType($type);
        $result = [];

        foreach ($drivers as $name => $driver) {
            $result[$name] = $driver->getDescription();
        }

        return $result;
    }

    /**
     * Get full schema for a specific driver.
     *
     * @param string $type Driver type
     * @param string $name Driver name
     *
     * @return array<string, mixed> Driver schema including fields
     *
     * @throws InvalidArgumentException If driver not found
     */
    public function getDriverSchema(string $type, string $name): array
    {
        $driver = $this->repository->get($type, $name);

        $fields = [];
        foreach ($driver->getFields() as $field) {
            $fields[] = $field->jsonSerialize();
        }

        return [
            'name' => $driver->getName(),
            'description' => $driver->getDescription(),
            'type' => $driver->getType(),
            'fields' => $fields,
        ];
    }

    /**
     * Validate configuration against a driver's requirements.
     *
     * @param string $type Driver type
     * @param string $name Driver name
     * @param array<string, mixed> $config Configuration data
     *
     * @return ValidationResult Validation result
     *
     * @throws InvalidArgumentException If driver not found
     */
    public function validateConfig(string $type, string $name, array $config): ValidationResult
    {
        $driver = $this->repository->get($type, $name);
        return $driver->validate($config);
    }

    /**
     * Export complete schema for all drivers.
     *
     * Useful for API endpoints, CLI tools, documentation generation.
     *
     * @return array<string, array<string, mixed>> Schema indexed by type and name
     */
    public function exportSchema(): array
    {
        $schema = [];

        foreach ($this->repository->getTypes() as $type) {
            $schema[$type] = [];

            foreach ($this->repository->getByType($type) as $name => $driver) {
                $schema[$type][$name] = $this->getDriverSchema($type, $name);
            }
        }

        return $schema;
    }

    /**
     * Convert driver schema to legacy Horde_Config format.
     *
     * Bridges new metadata system with existing Horde_Config arrays.
     *
     * @param string $type Driver type
     * @param string $name Driver name
     *
     * @return array<string, mixed> Legacy format configuration array
     *
     * @throws InvalidArgumentException If driver not found
     */
    public function toLegacyFormat(string $type, string $name): array
    {
        $driver = $this->repository->get($type, $name);
        $legacy = [];

        foreach ($driver->getFields() as $field) {
            $legacyField = [
                'desc' => $field->description,
                'type' => $field->type->value,
            ];

            if ($field->required) {
                $legacyField['required'] = true;
            }

            if ($field->default !== null) {
                $legacyField['default'] = $field->default;
            }

            if (!empty($field->options)) {
                $legacyField['values'] = $field->options;
            }

            if ($field->conditionalFields !== null) {
                $legacyField['fields'] = [];
                foreach ($field->conditionalFields->getCases() as $case) {
                    $caseFields = $field->conditionalFields->getFieldsForCase($case);
                    foreach ($caseFields as $caseField) {
                        $legacyField['fields'][$case][$caseField->name] = [
                            'desc' => $caseField->description,
                            'type' => $caseField->type->value,
                            'required' => $caseField->required,
                        ];
                        if ($caseField->default !== null) {
                            $legacyField['fields'][$case][$caseField->name]['default'] = $caseField->default;
                        }
                        if (!empty($caseField->options)) {
                            $legacyField['fields'][$case][$caseField->name]['values'] = $caseField->options;
                        }
                    }
                }
            }

            $legacy[$field->name] = $legacyField;
        }

        return $legacy;
    }
}
