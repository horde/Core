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
 * SQLite database driver configuration metadata.
 *
 * Provides field definitions and validation for SQLite connections
 * using PDO.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class SQLiteDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'sqlite';
    }

    public function getDescription(): string
    {
        return 'SQLite';
    }

    public function getType(): string
    {
        return 'sql';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'database',
                type: FieldType::TEXT,
                description: 'Path to SQLite database file (or :memory: for in-memory database)',
                required: true,
                default: '/var/lib/horde/horde.db',
            ),
            new PropertyMetadata(
                name: 'timeout',
                type: FieldType::INTEGER,
                description: 'Connection timeout in seconds',
                required: false,
                default: 5,
            ),
            new PropertyMetadata(
                name: 'mode',
                type: FieldType::ENUM,
                description: 'Database file mode',
                required: false,
                default: '0600',
                allowedValues: ['0600', '0640', '0660', '0644'],
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

        // Additional validation: check database path is not empty
        $database = $config['database'] ?? null;
        if ($database !== null && $database !== ':memory:' && empty(trim($database))) {
            $errors[] = 'Database path cannot be empty';
        }

        return new ValidationResult($errors);
    }
}
