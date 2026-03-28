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

namespace Horde\Core\Config\Driver\Sql;

use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\ConditionalFields;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * MySQL database driver configuration metadata.
 *
 * Provides field definitions and validation for MySQL connections
 * using PDO or mysqli.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class MySQLDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'mysql';
    }

    public function getDescription(): string
    {
        return 'MySQL / PDO';
    }

    public function getType(): string
    {
        return 'sql';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'username',
                type: FieldType::TEXT,
                description: 'Username to connect to the database as',
                required: true,
            ),
            new PropertyMetadata(
                name: 'password',
                type: FieldType::PASSWORD,
                description: 'Password to connect with',
                required: false,
            ),
            new PropertyMetadata(
                name: 'protocol',
                type: FieldType::SWITCH,
                description: 'How should we connect to the database?',
                required: true,
                default: 'tcp',
                conditionalFields: new ConditionalFields([
                    'tcp' => [
                        new PropertyMetadata(
                            name: 'hostspec',
                            type: FieldType::TEXT,
                            description: 'Database server/host',
                            required: true,
                            default: 'localhost',
                        ),
                        new PropertyMetadata(
                            name: 'port',
                            type: FieldType::INTEGER,
                            description: 'Database port',
                            required: false,
                            default: 3306,
                        ),
                    ],
                    'unix' => [
                        new PropertyMetadata(
                            name: 'socket',
                            type: FieldType::TEXT,
                            description: 'Unix socket path',
                            required: true,
                            default: '/var/run/mysqld/mysqld.sock',
                        ),
                    ],
                ]),
            ),
            new PropertyMetadata(
                name: 'database',
                type: FieldType::TEXT,
                description: 'Database name to use',
                required: true,
            ),
            new PropertyMetadata(
                name: 'charset',
                type: FieldType::TEXT,
                description: 'Internally used charset',
                required: true,
                default: 'utf8mb4',
            ),
            new PropertyMetadata(
                name: 'ssl',
                type: FieldType::SWITCH,
                description: 'Use SSL to connect to the server?',
                required: false,
                default: false,
                conditionalFields: new ConditionalFields([
                    'true' => [
                        new PropertyMetadata(
                            name: 'ca',
                            type: FieldType::TEXT,
                            description: 'Path to Certificate Authority file',
                            required: false,
                        ),
                        new PropertyMetadata(
                            name: 'cert',
                            type: FieldType::TEXT,
                            description: 'Path to SSL certificate file',
                            required: false,
                        ),
                        new PropertyMetadata(
                            name: 'key',
                            type: FieldType::TEXT,
                            description: 'Path to SSL key file',
                            required: false,
                        ),
                    ],
                ]),
            ),
            new PropertyMetadata(
                name: 'logqueries',
                type: FieldType::BOOLEAN,
                description: 'Should Horde log all queries',
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

        return new ValidationResult($errors);
    }
}
