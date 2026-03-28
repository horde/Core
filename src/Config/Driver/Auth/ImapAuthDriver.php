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
 * IMAP authentication driver configuration metadata.
 *
 * Provides field definitions and validation for IMAP-based authentication.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ImapAuthDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'imap';
    }

    public function getDescription(): string
    {
        return 'IMAP Server Authentication';
    }

    public function getType(): string
    {
        return 'auth';
    }

    public function getFields(): array
    {
        return [
            new PropertyMetadata(
                name: 'hostspec',
                type: FieldType::TEXT,
                description: 'IMAP server hostname or IP address',
                required: true,
                default: 'localhost',
            ),
            new PropertyMetadata(
                name: 'port',
                type: FieldType::INTEGER,
                description: 'IMAP server port (143 for IMAP, 993 for IMAP-SSL)',
                required: false,
                default: 143,
            ),
            new PropertyMetadata(
                name: 'secure',
                type: FieldType::ENUM,
                description: 'Encryption method to use',
                required: false,
                default: 'tls',
                enumValues: [
                    'none',
                    'tls',
                    'ssl',
                ],
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
