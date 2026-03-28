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

use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\FieldType;
use Horde\Core\Config\Metadata\PropertyMetadata;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * RADIUS authentication driver configuration metadata.
 *
 * Provides field definitions and validation for RADIUS-based authentication.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class RadiusAuthDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'radius';
    }

    public function getDescription(): string
    {
        return 'RADIUS Server Authentication';
    }

    public function getType(): string
    {
        return 'auth';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'host',
                type: FieldType::TEXT,
                description: 'RADIUS server hostname or IP address',
                required: true,
                default: 'localhost',
            ),
            new PropertyMetadata(
                name: 'port',
                type: FieldType::INTEGER,
                description: 'RADIUS server port',
                required: false,
            ),
            new PropertyMetadata(
                name: 'method',
                type: FieldType::ENUM,
                description: 'RADIUS authentication method',
                required: false,
                default: 'PAP',
                enumValues: [
                    'PAP',
                    'CHAP_MD5',
                    'MSCHAPv1',
                    'MSCHAPv2',
                ],
            ),
            new PropertyMetadata(
                name: 'secret',
                type: FieldType::PASSWORD,
                description: 'RADIUS shared secret (max 128 bytes used)',
                required: true,
            ),
            new PropertyMetadata(
                name: 'nas',
                type: FieldType::TEXT,
                description: 'RADIUS NAS identifier',
                required: false,
            ),
            new PropertyMetadata(
                name: 'suffix',
                type: FieldType::TEXT,
                description: 'Domain suffix to add to unqualified usernames',
                required: false,
            ),
            new PropertyMetadata(
                name: 'timeout',
                type: FieldType::INTEGER,
                description: 'Timeout for server replies (seconds)',
                required: false,
                default: 3,
            ),
            new PropertyMetadata(
                name: 'retries',
                type: FieldType::INTEGER,
                description: 'Maximum retry attempts before giving up',
                required: false,
                default: 3,
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
}
