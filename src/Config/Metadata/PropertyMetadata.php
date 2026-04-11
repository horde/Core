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
 * Metadata for a single configuration property.
 *
 * Describes the type, validation rules, defaults, and documentation
 * for a configuration field.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class PropertyMetadata implements JsonSerializable
{
    /**
     * @param array<string, mixed> $options Available options for ENUM/MULTI_ENUM types
     * @param array<ValidationRule> $validationRules Custom validation rules
     */
    public function __construct(
        public readonly string $name,
        public readonly FieldType $type,
        public readonly string $description = '',
        public readonly bool $required = false,
        public readonly mixed $default = null,
        public readonly array $options = [],
        public readonly ?ConditionalFields $conditionalFields = null,
        public readonly array $validationRules = [],
    ) {}

    /**
     * Validate a value against this property's metadata.
     */
    public function validate(mixed $value): ValidationResult
    {
        $errors = [];

        // Check if required field is missing
        if ($this->required && ($value === null || $value === '')) {
            $errors[] = "Property '{$this->name}' is required but not provided";
            return new ValidationResult($errors);
        }

        // Skip further validation if value is null and not required
        if ($value === null || $value === '') {
            return new ValidationResult([]);
        }

        // Type validation
        $typeResult = $this->validateType($value);
        if (!$typeResult->isValid()) {
            $errors = array_merge($errors, $typeResult->getErrors());
        }

        // Custom validation rules
        foreach ($this->validationRules as $rule) {
            $ruleResult = $rule->validate($value);
            if (!$ruleResult->isValid()) {
                $errors = array_merge($errors, $ruleResult->getErrors());
            }
        }

        return new ValidationResult($errors);
    }

    /**
     * Validate value against the field type.
     */
    private function validateType(mixed $value): ValidationResult
    {
        $errors = [];

        match ($this->type) {
            FieldType::INTEGER => $this->validateInteger($value, $errors),
            FieldType::BOOLEAN => $this->validateBoolean($value, $errors),
            FieldType::ENUM => $this->validateEnum($value, $errors),
            FieldType::MULTI_ENUM => $this->validateMultiEnum($value, $errors),
            default => null,
        };

        return new ValidationResult($errors);
    }

    /**
     * Validate integer type.
     *
     * @param array<string> $errors
     */
    private function validateInteger(mixed $value, array &$errors): void
    {
        if (!is_int($value) && !is_numeric($value)) {
            $errors[] = "Property '{$this->name}' must be an integer, got: " . gettype($value);
        }
    }

    /**
     * Validate boolean type.
     *
     * @param array<string> $errors
     */
    private function validateBoolean(mixed $value, array &$errors): void
    {
        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            $errors[] = "Property '{$this->name}' must be a boolean, got: " . gettype($value);
        }
    }

    /**
     * Validate enum type.
     *
     * @param array<string> $errors
     */
    private function validateEnum(mixed $value, array &$errors): void
    {
        if (!isset($this->options[$value])) {
            $errors[] = "Property '{$this->name}' must be one of: " . implode(', ', array_keys($this->options));
        }
    }

    /**
     * Validate multi-enum type.
     *
     * @param array<string> $errors
     */
    private function validateMultiEnum(mixed $value, array &$errors): void
    {
        if (!is_array($value)) {
            $errors[] = "Property '{$this->name}' must be an array";
            return;
        }

        foreach ($value as $item) {
            if (!isset($this->options[$item])) {
                $errors[] = "Property '{$this->name}' contains invalid value: {$item}";
            }
        }
    }

    /**
     * Export metadata as array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'name' => $this->name,
            'type' => $this->type->value,
            'description' => $this->description,
            'required' => $this->required,
        ];

        if ($this->default !== null) {
            $data['default'] = $this->default;
        }

        if (!empty($this->options)) {
            $data['options'] = $this->options;
        }

        if ($this->conditionalFields !== null) {
            $data['conditionalFields'] = $this->conditionalFields;
        }

        return $data;
    }
}
