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

namespace Horde\Core\Config\Driver\Prefs;

use Horde\Core\Config\Driver\DriverAvailability;
use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * NoSQL preferences driver configuration metadata.
 *
 * Provides field definitions and validation for NoSQL-based preferences storage.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class NoSqlPrefsDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'nosql';
    }

    public function getDescription(): string
    {
        return 'NoSQL Database';
    }

    public function getType(): string
    {
        return 'prefs';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'driverconfig',
                type: FieldType::TEXT,
                description: 'NoSQL connection configuration (references nosql driver)',
                required: true,
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

        return new ValidationResult($errors);
    }

    public function checkAvailability(): DriverAvailability
    {
        return DriverAvailability::available();
    }
}
