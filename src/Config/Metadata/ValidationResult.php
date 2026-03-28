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

namespace Horde\Core\Config\Metadata;

/**
 * Result of validating configuration data.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ValidationResult
{
    /**
     * @param array<string> $errors List of validation error messages
     */
    public function __construct(
        private readonly array $errors = [],
    ) {}

    /**
     * Check if validation passed.
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get list of error messages.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message, or null if no errors.
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }
}
