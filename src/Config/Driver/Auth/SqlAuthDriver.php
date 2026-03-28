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

namespace Horde\Core\Config\Driver\Auth;

use Horde\Core\Config\Driver\DriverAvailability;
use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * SQL authentication driver configuration metadata.
 *
 * Provides field definitions and validation for SQL-based authentication.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class SqlAuthDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'sql';
    }

    public function getDescription(): string
    {
        return 'SQL Database Authentication';
    }

    public function getType(): string
    {
        return 'auth';
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
                description: 'Authentication table name',
                required: false,
                default: 'horde_users',
            ),
            new PropertyMetadata(
                name: 'username_field',
                type: FieldType::TEXT,
                description: 'Username column name',
                required: false,
                default: 'user_uid',
            ),
            new PropertyMetadata(
                name: 'password_field',
                type: FieldType::TEXT,
                description: 'Password column name',
                required: false,
                default: 'user_pass',
            ),
            new PropertyMetadata(
                name: 'encryption',
                type: FieldType::ENUM,
                description: 'Password hashing algorithm',
                required: false,
                default: 'ssha',
                enumValues: [
                    'aprmd5',
                    'crypt',
                    'crypt-blowfish',
                    'crypt-des',
                    'crypt-md5',
                    'crypt-sha256',
                    'crypt-sha512',
                    'md5-base64',
                    'md5-hex',
                    'plain',
                    'sha',
                    'smd5',
                    'ssha',
                ],
            ),
            new PropertyMetadata(
                name: 'soft_expiration_field',
                type: FieldType::TEXT,
                description: 'Column containing soft password expiration timestamp',
                required: false,
            ),
            new PropertyMetadata(
                name: 'hard_expiration_field',
                type: FieldType::TEXT,
                description: 'Column containing hard password expiration timestamp',
                required: false,
            ),
            new PropertyMetadata(
                name: 'soft_expiration_window',
                type: FieldType::INTEGER,
                description: 'Days until password must be changed (soft)',
                required: false,
            ),
            new PropertyMetadata(
                name: 'hard_expiration_window',
                type: FieldType::INTEGER,
                description: 'Grace period days after soft expiration (hard)',
                required: false,
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
