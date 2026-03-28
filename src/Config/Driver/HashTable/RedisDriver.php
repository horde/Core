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
 * Redis driver configuration metadata for HashTable.
 *
 * Provides field definitions and validation for Redis connections
 * used as distributed hash table / caching backend.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class RedisDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'predis';
    }

    public function getDescription(): string
    {
        return 'Redis Server';
    }

    public function getType(): string
    {
        return 'hashtable';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'protocol',
                type: FieldType::SWITCH,
                description: 'How should we connect to Redis?',
                required: true,
                default: 'tcp',
                conditionalFields: new ConditionalFields([
                    'tcp' => [
                        new PropertyMetadata(
                            name: 'hostspec',
                            type: FieldType::TEXT,
                            description: 'Redis server hostname or IP address',
                            required: true,
                            default: 'localhost',
                        ),
                        new PropertyMetadata(
                            name: 'port',
                            type: FieldType::INTEGER,
                            description: 'Redis server port',
                            required: false,
                            default: 6379,
                        ),
                    ],
                    'unix' => [
                        new PropertyMetadata(
                            name: 'socket',
                            type: FieldType::TEXT,
                            description: 'Unix socket path',
                            required: true,
                            default: '/var/run/redis/redis.sock',
                        ),
                    ],
                ]),
            ),
            new PropertyMetadata(
                name: 'password',
                type: FieldType::PASSWORD,
                description: 'Redis authentication password',
                required: false,
            ),
            new PropertyMetadata(
                name: 'database',
                type: FieldType::INTEGER,
                description: 'Redis database number (0-15)',
                required: false,
                default: 0,
            ),
            new PropertyMetadata(
                name: 'timeout',
                type: FieldType::INTEGER,
                description: 'Connection timeout in seconds',
                required: false,
                default: 5,
            ),
            new PropertyMetadata(
                name: 'persistent',
                type: FieldType::BOOLEAN,
                description: 'Use persistent connections',
                required: false,
                default: false,
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

        // Additional validation: database number must be 0-15
        $database = $config['database'] ?? 0;
        if ($database < 0 || $database > 15) {
            $errors[] = 'Redis database number must be between 0 and 15';
        }

        return new ValidationResult($errors);
    }
}
