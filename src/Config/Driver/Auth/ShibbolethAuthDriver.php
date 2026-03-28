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
 * Shibboleth authentication driver configuration metadata.
 *
 * Provides field definitions and validation for Shibboleth SSO authentication.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ShibbolethAuthDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'shibboleth';
    }

    public function getDescription(): string
    {
        return 'Shibboleth SSO Authentication';
    }

    public function getType(): string
    {
        return 'auth';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'username_header',
                type: FieldType::TEXT,
                description: 'HTTP header containing the username',
                required: false,
                default: 'REMOTE_USER',
            ),
            new PropertyMetadata(
                name: 'password_holder',
                type: FieldType::SWITCH,
                description: 'Where to get the password for hordeauth',
                required: false,
                default: 'none',
                conditionalFields: new ConditionalFields([
                    'header' => [
                        new PropertyMetadata(
                            name: 'password_header',
                            type: FieldType::TEXT,
                            description: 'HTTP header containing the password',
                            required: false,
                        ),
                    ],
                    'preferences' => [
                        new PropertyMetadata(
                            name: 'password_preference',
                            type: FieldType::TEXT,
                            description: 'Horde preference containing the password',
                            required: false,
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
