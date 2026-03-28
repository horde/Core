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

namespace Horde\Core\Config\Driver\HashTable;

use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\ConditionalFields;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * Memcache driver configuration metadata for HashTable.
 *
 * Provides field definitions and validation for Memcache connections
 * used as distributed hash table / caching backend.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class MemcacheDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'memcache';
    }

    public function getDescription(): string
    {
        return 'Memcache Server';
    }

    public function getType(): string
    {
        return 'hashtable';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'hostspec',
                type: FieldType::TEXT,
                description: 'Memcache server hostname(s) or IP address(es), comma-separated',
                required: true,
                default: 'localhost',
            ),
            new PropertyMetadata(
                name: 'port',
                type: FieldType::TEXT,
                description: 'Memcache server port(s), comma-separated if multiple hosts',
                required: false,
                default: '11211',
            ),
            new PropertyMetadata(
                name: 'weight',
                type: FieldType::TEXT,
                description: 'Weight for each server, comma-separated if multiple hosts',
                required: false,
            ),
            new PropertyMetadata(
                name: 'persistent',
                type: FieldType::BOOLEAN,
                description: 'Enable persistent connections to server(s)',
                required: false,
                default: false,
            ),
            new PropertyMetadata(
                name: 'compression',
                type: FieldType::SWITCH,
                description: 'Enable compression when storing entries?',
                required: false,
                default: false,
                conditionalFields: new ConditionalFields([
                    'true' => [
                        new PropertyMetadata(
                            name: 'c_threshold',
                            type: FieldType::INTEGER,
                            description: 'Threshold length before compressing data (bytes)',
                            required: false,
                            default: 2000,
                        ),
                    ],
                ]),
            ),
        ];
    }

    public function validate(array $config): ValidationResult
    {
        $errors = [];

        // Validate each field
        foreach ($this->getFields() as $field) {
            $value = $config[$field->name] ?? null;
            $result = $field->validate($value);

            if (!$result->isValid()) {
                $errors = array_merge($errors, $result->getErrors());
            }

            // Validate conditional fields if this is a switch
            if ($field->type === FieldType::SWITCH && $field->conditionalFields !== null) {
                $switchValue = $value ?? $field->default;
                if ($switchValue !== null) {
                    $conditionalFields = $field->conditionalFields->getFieldsForCase((string) $switchValue);
                    foreach ($conditionalFields as $conditionalField) {
                        $conditionalValue = $config[$conditionalField->name] ?? null;
                        $conditionalResult = $conditionalField->validate($conditionalValue);

                        if (!$conditionalResult->isValid()) {
                            $errors = array_merge($errors, $conditionalResult->getErrors());
                        }
                    }
                }
            }
        }

        return new ValidationResult($errors);
    }
}
