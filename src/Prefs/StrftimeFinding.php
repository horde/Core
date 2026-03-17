<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Prefs;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Result object for strftime pattern detection
 *
 * Represents a single finding of a deprecated strftime pattern
 * in preference definitions.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class StrftimeFinding implements JsonSerializable
{
    public const CONFIDENCE_HIGH = 'high';
    public const CONFIDENCE_MEDIUM = 'medium';
    public const CONFIDENCE_LOW = 'low';

    /**
     * Constructor
     *
     * @param string $pref Preference name
     * @param string $field Field path (e.g., 'value', 'enum.key')
     * @param string $location Human-readable location
     * @param string $strftime Detected strftime pattern
     * @param string|array $icu ICU pattern or array for locale-specific
     * @param string $confidence Confidence level (high, medium, low)
     * @throws InvalidArgumentException if confidence level is invalid
     */
    public function __construct(
        public readonly string $pref,
        public readonly string $field,
        public readonly string $location,
        public readonly string $strftime,
        public readonly string|array $icu,
        public readonly string $confidence,
    ) {
        if (!in_array($confidence, [self::CONFIDENCE_HIGH, self::CONFIDENCE_MEDIUM, self::CONFIDENCE_LOW], true)) {
            throw new InvalidArgumentException("Invalid confidence level: $confidence");
        }
    }

    /**
     * Check if ICU pattern is locale-specific
     *
     * @return bool True if pattern depends on user locale
     */
    public function isLocaleSpecific(): bool
    {
        return is_array($this->icu);
    }

    /**
     * Get ICU pattern as string
     *
     * For locale-specific patterns, returns a description.
     * For concrete patterns, returns the pattern itself.
     *
     * @return string ICU pattern or description
     */
    public function getIcuString(): string
    {
        if (is_array($this->icu)) {
            return '[locale-specific: ' . implode(', ', array_keys($this->icu)) . ']';
        }
        return $this->icu;
    }

    /**
     * Format as human-readable string
     *
     * @return string Formatted finding
     */
    public function format(): string
    {
        return sprintf(
            "%s: Found '%s' → suggest '%s' (%s confidence)",
            $this->location,
            $this->strftime,
            $this->getIcuString(),
            $this->confidence
        );
    }

    /**
     * Convert to associative array
     *
     * @return array Array representation
     */
    public function toArray(): array
    {
        return [
            'pref' => $this->pref,
            'field' => $this->field,
            'location' => $this->location,
            'strftime' => $this->strftime,
            'icu' => $this->icu,
            'confidence' => $this->confidence,
        ];
    }

    /**
     * JSON serialization
     *
     * @return array Data for JSON encoding
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Static factory from array (for backward compatibility)
     *
     * @param array $data Array with keys: pref, field, location, strftime, icu, confidence
     * @return self New instance
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pref: $data['pref'],
            field: $data['field'],
            location: $data['location'],
            strftime: $data['strftime'],
            icu: $data['icu'],
            confidence: $data['confidence'],
        );
    }
}
