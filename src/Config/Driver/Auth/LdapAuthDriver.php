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
use Horde\Core\Config\Metadata\ConditionalFields;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * LDAP authentication driver configuration metadata.
 *
 * Provides field definitions and validation for LDAP-based authentication.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class LdapAuthDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'ldap';
    }

    public function getDescription(): string
    {
        return 'LDAP Directory Authentication';
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
                description: 'LDAP connection configuration (references ldap driver)',
                required: true,
            ),
            new PropertyMetadata(
                name: 'uid',
                type: FieldType::TEXT,
                description: 'Username search attribute (use samaccountname for AD)',
                required: true,
            ),
            new PropertyMetadata(
                name: 'sizelimit',
                type: FieldType::INTEGER,
                description: 'Size limit for listing users on large directories',
                required: false,
            ),
            new PropertyMetadata(
                name: 'ad',
                type: FieldType::BOOLEAN,
                description: 'Is this an Active Directory server?',
                required: false,
                default: false,
            ),
            new PropertyMetadata(
                name: 'encryption',
                type: FieldType::ENUM,
                description: 'Password hashing algorithm',
                required: false,
                default: 'ssha',
                options: [
                    'aprmd5' => 'APR MD5',
                    'crypt' => 'Crypt',
                    'crypt-blowfish' => 'Crypt Blowfish',
                    'crypt-des' => 'Crypt DES',
                    'crypt-md5' => 'Crypt MD5',
                    'crypt-sha256' => 'Crypt SHA-256',
                    'crypt-sha512' => 'Crypt SHA-512',
                    'md5-base64' => 'MD5 Base64',
                    'md5-hex' => 'MD5 Hex',
                    'msad' => 'MS Active Directory',
                    'plain' => 'Plain Text',
                    'sha' => 'SHA',
                    'smd5' => 'SMD5',
                    'ssha' => 'SSHA',
                ],
            ),
            new PropertyMetadata(
                name: 'newuser_objectclass',
                type: FieldType::TEXT,
                description: 'Objectclasses for new users (comma-separated)',
                required: false,
                default: 'shadowAccount,inetOrgPerson',
            ),
            new PropertyMetadata(
                name: 'filter',
                type: FieldType::TEXT,
                description: 'LDAP filter for searching users',
                required: false,
                default: '(objectclass=shadowAccount)',
            ),
            new PropertyMetadata(
                name: 'password_expiration',
                type: FieldType::SWITCH,
                description: 'Enable password expiration for new accounts?',
                required: false,
                default: 'no',
                conditionalFields: new ConditionalFields([
                    'yes' => [
                        new PropertyMetadata(
                            name: 'minage',
                            type: FieldType::INTEGER,
                            description: 'Minimum days before password can be changed',
                            required: false,
                            default: 5,
                        ),
                        new PropertyMetadata(
                            name: 'maxage',
                            type: FieldType::INTEGER,
                            description: 'Days until password expires',
                            required: false,
                            default: 30,
                        ),
                        new PropertyMetadata(
                            name: 'warnage',
                            type: FieldType::INTEGER,
                            description: 'Days before expiration to warn user',
                            required: false,
                            default: 5,
                        ),
                    ],
                ]),
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
