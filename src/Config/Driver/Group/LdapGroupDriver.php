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

namespace Horde\Core\Config\Driver\Group;

use Horde\Core\Config\Driver\DriverAvailability;
use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\ConditionalFields;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * LDAP group driver configuration metadata.
 *
 * Provides field definitions and validation for LDAP-based group management.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class LdapGroupDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'ldap';
    }

    public function getDescription(): string
    {
        return 'LDAP Directory';
    }

    public function getType(): string
    {
        return 'group';
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
                name: 'gid',
                type: FieldType::TEXT,
                description: 'Group search key attribute',
                required: false,
                default: 'cn',
            ),
            new PropertyMetadata(
                name: 'memberuid',
                type: FieldType::TEXT,
                description: 'Group membership field attribute',
                required: false,
                default: 'memberUid',
            ),
            new PropertyMetadata(
                name: 'newgroup_objectclass',
                type: FieldType::TEXT,
                description: 'Objectclasses for new groups (comma-separated)',
                required: false,
                default: 'posixGroup,hordeGroup',
            ),
            new PropertyMetadata(
                name: 'writedn',
                type: FieldType::TEXT,
                description: 'DN for bind when creating/editing groups',
                required: false,
            ),
            new PropertyMetadata(
                name: 'writepw',
                type: FieldType::PASSWORD,
                description: 'Password for write bind DN',
                required: false,
            ),
            new PropertyMetadata(
                name: 'attrisdn',
                type: FieldType::SWITCH,
                description: 'Are member attributes fully qualified DNs?',
                required: false,
                default: 'false',
                conditionalFields: new ConditionalFields([
                    'true' => [
                        new PropertyMetadata(
                            name: 'user_dn',
                            type: FieldType::TEXT,
                            description: 'User DN configuration',
                            required: false,
                        ),
                    ],
                ]),
            ),
            new PropertyMetadata(
                name: 'filter_type',
                type: FieldType::SWITCH,
                description: 'Group filter specification method',
                required: false,
                default: 'objectclass',
                conditionalFields: new ConditionalFields([
                    'objectclass' => [
                        new PropertyMetadata(
                            name: 'objectclass',
                            type: FieldType::TEXT,
                            description: 'Objectclass filter (comma-separated)',
                            required: false,
                            default: 'posixGroup',
                        ),
                    ],
                    'filter' => [
                        new PropertyMetadata(
                            name: 'filter',
                            type: FieldType::TEXT,
                            description: 'LDAP RFC formatted filter expression',
                            required: true,
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
