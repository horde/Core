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

namespace Horde\Core\Config\Driver\NoSql;

use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\ConditionalFields;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * MongoDB driver configuration metadata.
 *
 * Provides field definitions and validation for MongoDB connections.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class MongoDBDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'mongodb';
    }

    public function getDescription(): string
    {
        return 'MongoDB';
    }

    public function getType(): string
    {
        return 'nosql';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'hostspec',
                type: FieldType::TEXT,
                description: 'MongoDB server hostname or IP address',
                required: true,
                default: 'localhost',
            ),
            new PropertyMetadata(
                name: 'port',
                type: FieldType::INTEGER,
                description: 'MongoDB server port',
                required: false,
                default: 27017,
            ),
            new PropertyMetadata(
                name: 'database',
                type: FieldType::TEXT,
                description: 'Database name to use',
                required: true,
            ),
            new PropertyMetadata(
                name: 'username',
                type: FieldType::TEXT,
                description: 'Username to authenticate as',
                required: false,
            ),
            new PropertyMetadata(
                name: 'password',
                type: FieldType::PASSWORD,
                description: 'Password to authenticate with',
                required: false,
            ),
            new PropertyMetadata(
                name: 'authdb',
                type: FieldType::TEXT,
                description: 'Authentication database',
                required: false,
                default: 'admin',
            ),
            new PropertyMetadata(
                name: 'ssl',
                type: FieldType::SWITCH,
                description: 'Use SSL/TLS to connect?',
                required: false,
                default: false,
                conditionalFields: new ConditionalFields([
                    'true' => [
                        new PropertyMetadata(
                            name: 'ca_file',
                            type: FieldType::TEXT,
                            description: 'Path to CA certificate file',
                            required: false,
                        ),
                        new PropertyMetadata(
                            name: 'pem_file',
                            type: FieldType::TEXT,
                            description: 'Path to PEM certificate file',
                            required: false,
                        ),
                    ],
                ]),
            ),
            new PropertyMetadata(
                name: 'replica_set',
                type: FieldType::TEXT,
                description: 'Replica set name (for replica sets)',
                required: false,
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
