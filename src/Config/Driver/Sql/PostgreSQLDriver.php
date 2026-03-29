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

use Horde\Core\Config\Driver\DriverAvailability;
use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\ConditionalFields;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * PostgreSQL database driver configuration metadata.
 *
 * Provides field definitions and validation for PostgreSQL connections
 * using PDO.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class PostgreSQLDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'pgsql';
    }

    public function getDescription(): string
    {
        return 'PostgreSQL';
    }

    public function getType(): string
    {
        return 'sql';
    }

    public function getFields(): array
    {
        return [
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
                default: 5432,
            ),
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
                default: 'utf8',
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
                            name: 'sslmode',
                            type: FieldType::ENUM,
                            description: 'SSL mode',
                            required: false,
                            default: 'require',
                            options: [
                                'disable' => 'Disable',
                                'allow' => 'Allow',
                                'prefer' => 'Prefer',
                                'require' => 'Require',
                                'verify-ca' => 'Verify CA',
                                'verify-full' => 'Verify Full',
                            ],
                        ),
                        new PropertyMetadata(
                            name: 'sslcert',
                            type: FieldType::TEXT,
                            description: 'Path to SSL certificate file',
                            required: false,
                        ),
                        new PropertyMetadata(
                            name: 'sslkey',
                            type: FieldType::TEXT,
                            description: 'Path to SSL key file',
                            required: false,
                        ),
                        new PropertyMetadata(
                            name: 'sslrootcert',
                            type: FieldType::TEXT,
                            description: 'Path to SSL root certificate file',
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
    public function checkAvailability(): DriverAvailability
    {
        return DriverAvailability::available();
    }

}
