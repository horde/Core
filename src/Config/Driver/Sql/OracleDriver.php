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
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * Oracle database driver configuration metadata.
 *
 * Provides field definitions and validation for Oracle connections
 * using PDO.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class OracleDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'oci8';
    }

    public function getDescription(): string
    {
        return 'Oracle';
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
                default: 1521,
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
                name: 'service',
                type: FieldType::TEXT,
                description: 'Oracle service name (TNS)',
                required: true,
            ),
            new PropertyMetadata(
                name: 'charset',
                type: FieldType::TEXT,
                description: 'Internally used charset',
                required: true,
                default: 'AL32UTF8',
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
        }

        return new ValidationResult($errors);
    }
}
