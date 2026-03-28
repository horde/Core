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
 * HTTP (Basic Auth) authentication driver configuration metadata.
 *
 * Provides field definitions and validation for HTTP/.htpasswd authentication.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class HttpAuthDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'http';
    }

    public function getDescription(): string
    {
        return 'HTTP Basic Authentication / .htpasswd';
    }

    public function getType(): string
    {
        return 'auth';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'htpasswd_file',
                type: FieldType::TEXT,
                description: 'Path to htpasswd file',
                required: false,
            ),
            new PropertyMetadata(
                name: 'encryption',
                type: FieldType::ENUM,
                description: 'Password hashing algorithm used in htpasswd file',
                required: false,
                default: 'crypt-des',
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
                name: 'show_encryption',
                type: FieldType::BOOLEAN,
                description: 'Prepend password algorithm to password value?',
                required: false,
                default: true,
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
