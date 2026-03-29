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

namespace Horde\Core\Config\Driver\Alarms;

use Horde\Core\Config\Driver\DriverAvailability;
use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * SQL alarms driver configuration metadata.
 *
 * Provides field definitions and validation for SQL-based alarm storage.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class SqlAlarmsDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'sql';
    }

    public function getDescription(): string
    {
        return 'SQL Database';
    }

    public function getType(): string
    {
        return 'alarms';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'driverconfig',
                type: FieldType::TEXT,
                description: 'SQL connection configuration (references sql driver)',
                required: true,
            ),
            new PropertyMetadata(
                name: 'table',
                type: FieldType::TEXT,
                description: 'Alarms table name',
                required: false,
                default: 'horde_alarms',
            ),
            new PropertyMetadata(
                name: 'ttl',
                type: FieldType::INTEGER,
                description: 'How often to query applications for new alarms (seconds)',
                required: false,
                default: 300,
            ),
        ];
    }

    public function validate(array $config): ValidationResult
    {
        $errors = [];

        foreach ($this->getFields() as $field) {
            $value = $config[$field->name] ?? null;
            $result = $field->validate($value);

            if (!$result->isValid()) {
                $errors = array_merge($errors, $result->getErrors());
            }
        }

        // Additional validation: TTL should be positive
        if (isset($config['ttl']) && $config['ttl'] < 1) {
            $errors[] = 'TTL must be at least 1 second';
        }

        return new ValidationResult($errors);
    }

    public function checkAvailability(): DriverAvailability
    {
        return DriverAvailability::available();
    }
}
