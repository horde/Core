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

use JsonSerializable;

/**
 * Conditional fields based on switch/case values.
 *
 * Used when a SWITCH field type determines which additional
 * fields should be shown/validated.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ConditionalFields implements JsonSerializable
{
    /**
     * @param array<string, array<PropertyMetadata>> $cases Map of case values to field arrays
     */
    public function __construct(
        private readonly array $cases = [],
    ) {}

    /**
     * Get fields for a specific case value.
     *
     * @return array<PropertyMetadata>
     */
    public function getFieldsForCase(string $caseValue): array
    {
        return $this->cases[$caseValue] ?? [];
    }

    /**
     * Get all available cases.
     *
     * @return array<string>
     */
    public function getCases(): array
    {
        return array_keys($this->cases);
    }

    /**
     * Check if a case exists.
     */
    public function hasCase(string $caseValue): bool
    {
        return isset($this->cases[$caseValue]);
    }

    /**
     * Export as array for JSON serialization.
     *
     * @return array<string, array<mixed>>
     */
    public function jsonSerialize(): array
    {
        $result = [];
        foreach ($this->cases as $caseValue => $fields) {
            $result[$caseValue] = array_map(
                fn(PropertyMetadata $field) => $field->jsonSerialize(),
                $fields
            );
        }
        return $result;
    }
}
