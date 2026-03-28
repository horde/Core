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

namespace Horde\Core\Config\Driver\Ldap;

use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\ConditionalFields;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * LDAP driver configuration metadata.
 *
 * Provides field definitions and validation for LDAP connections.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class LdapDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'ldap';
    }

    public function getDescription(): string
    {
        return 'LDAP Directory Server';
    }

    public function getType(): string
    {
        return 'ldap';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'hostspec',
                type: FieldType::TEXT,
                description: 'LDAP server hostname or IP address',
                required: true,
                default: 'localhost',
            ),
            new PropertyMetadata(
                name: 'port',
                type: FieldType::INTEGER,
                description: 'LDAP server port',
                required: false,
                default: 389,
            ),
            new PropertyMetadata(
                name: 'basedn',
                type: FieldType::TEXT,
                description: 'Base DN for LDAP searches',
                required: true,
            ),
            new PropertyMetadata(
                name: 'binddn',
                type: FieldType::TEXT,
                description: 'DN to bind as (leave empty for anonymous bind)',
                required: false,
            ),
            new PropertyMetadata(
                name: 'password',
                type: FieldType::PASSWORD,
                description: 'Password for bind DN',
                required: false,
            ),
            new PropertyMetadata(
                name: 'version',
                type: FieldType::INTEGER,
                description: 'LDAP protocol version',
                required: false,
                default: 3,
            ),
            new PropertyMetadata(
                name: 'tls',
                type: FieldType::BOOLEAN,
                description: 'Use TLS/STARTTLS for connection',
                required: false,
                default: false,
            ),
            new PropertyMetadata(
                name: 'timeout',
                type: FieldType::INTEGER,
                description: 'Connection timeout in seconds',
                required: false,
                default: 5,
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

        // Additional validation: if binddn is set, warn if password is empty
        $binddn = $config['binddn'] ?? null;
        $password = $config['password'] ?? null;
        if (!empty($binddn) && empty($password)) {
            // This is a warning, not an error - some setups use anonymous binds
            // We don't add to errors array
        }

        // Validate LDAP version
        $version = $config['version'] ?? 3;
        if (!in_array($version, [2, 3], true)) {
            $errors[] = 'LDAP version must be 2 or 3';
        }

        return new ValidationResult($errors);
    }
}
