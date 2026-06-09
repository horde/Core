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

use Horde\Core\Config\PrefsState;
use Horde\Date\Format as DateFormat;
use Closure;

/**
 * Detector for strftime format patterns in preference definitions
 *
 * Scans preference arrays from PrefsConfigLoader for deprecated
 * strftime patterns and suggests ICU replacements.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class StrftimeDetector
{
    /**
     * Default scan options
     */
    protected array $defaultOptions = [
        'check_enum_keys' => true,
        'check_enum_values' => false,
        'check_value' => true,
    ];

    /**
     * Scan preference definitions for strftime patterns
     *
     * @param PrefsState $prefs Preference state from PrefsConfigLoader
     * @param array|null $prefNames Specific prefs to check (null = all)
     * @param array $options Scan options (check_enum_keys, check_enum_values, check_value)
     * @return StrftimeFinding[] Array of findings
     */
    public function scan(
        PrefsState $prefs,
        ?array $prefNames = null,
        array $options = []
    ): array {
        $options = array_merge($this->defaultOptions, $options);
        $findings = [];

        // Get all preference definitions
        $allPrefs = $prefs->getAllPrefs();

        // Filter to requested prefs if specified
        if ($prefNames !== null) {
            $allPrefs = array_intersect_key(
                $allPrefs,
                array_flip($prefNames)
            );
        }

        // Scan each preference
        foreach ($allPrefs as $prefName => $prefDef) {
            $prefFindings = $this->scanPref($prefName, $prefDef, $options);
            $findings = array_merge($findings, $prefFindings);
        }

        return $findings;
    }

    /**
     * Scan a single preference definition
     *
     * @param string $prefName Preference name
     * @param array $prefDef Preference definition
     * @param array $options Scan options
     * @return StrftimeFinding[] Array of findings for this pref
     */
    public function scanPref(
        string $prefName,
        array $prefDef,
        array $options = []
    ): array {
        $options = array_merge($this->defaultOptions, $options);
        $findings = [];

        // Check 'value' field
        if ($options['check_value'] && isset($prefDef['value'])) {
            $value = $prefDef['value'];

            // Skip closures - can't analyze runtime values
            if (!($value instanceof Closure) && is_string($value)) {
                if ($this->isStrftimePattern($value)) {
                    $findings[] = new StrftimeFinding(
                        pref: $prefName,
                        field: 'value',
                        location: "{$prefName}['value']",
                        strftime: $value,
                        icu: $this->buildIcu($value),
                        confidence: $this->calculateConfidence($value),
                    );
                }
            }
        }

        // Check 'enum' field
        if (isset($prefDef['enum']) && is_array($prefDef['enum'])) {
            foreach ($prefDef['enum'] as $key => $value) {
                // Check enum keys
                if ($options['check_enum_keys'] && is_string($key)) {
                    if ($this->isStrftimePattern($key)) {
                        $findings[] = new StrftimeFinding(
                            pref: $prefName,
                            field: 'enum.key',
                            location: "{$prefName}['enum']['{$key}']",
                            strftime: $key,
                            icu: $this->buildIcu($key),
                            confidence: $this->calculateConfidence($key),
                        );
                    }
                }

                // Check enum values (usually not needed)
                if ($options['check_enum_values'] && is_string($value)) {
                    if ($this->isStrftimePattern($value)) {
                        $findings[] = new StrftimeFinding(
                            pref: $prefName,
                            field: 'enum.value',
                            location: "{$prefName}['enum']['{$key}'] (value)",
                            strftime: $value,
                            icu: $this->buildIcu($value),
                            confidence: $this->calculateConfidence($value),
                        );
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * Check if a string looks like a strftime pattern
     *
     * @param string $value Value to check
     * @return bool True if appears to be strftime format
     */
    protected function isStrftimePattern(string $value): bool
    {
        // Use Format class detection
        return DateFormat::isStrftimeFormat($value);
    }

    /**
     * Locales for which we resolve %x / %X / %c when reporting findings.
     *
     * Two locales with materially different conventions is enough to
     * make it visible to the reader that the pattern is locale-bound;
     * the goal is detection guidance, not exhaustive ICU output.
     */
    private const LOCALE_SPECIFIC_LOCALES = ['en_US', 'de_DE'];

    /**
     * Compute the ICU equivalent for a strftime pattern.
     *
     * Returns a single ICU string for ordinary patterns, or a
     * locale => ICU map when the pattern contains locale-specific
     * tokens (%x, %X, %c) whose meaning differs per locale.
     *
     * @param string $strftime Detected strftime pattern.
     * @return string|array<string,string>
     */
    protected function buildIcu(string $strftime): string|array
    {
        if (!preg_match('/%[xXc]/', $strftime)) {
            return DateFormat::strftimeToIcu($strftime);
        }

        $byLocale = [];
        foreach (self::LOCALE_SPECIFIC_LOCALES as $locale) {
            $byLocale[$locale] = DateFormat::strftimeToIcu($strftime, $locale);
        }
        return $byLocale;
    }

    /**
     * Calculate confidence level for a strftime pattern
     *
     * @param string $pattern Strftime pattern
     * @return string Confidence level (high, medium, low)
     */
    protected function calculateConfidence(string $pattern): string
    {
        // Count strftime specifiers (%Y, %m, %d, etc.)
        $specifierCount = preg_match_all('/%[a-zA-Z]/', $pattern);

        if ($specifierCount >= 3) {
            // Multiple specifiers: %Y-%m-%d
            return StrftimeFinding::CONFIDENCE_HIGH;
        } elseif ($specifierCount === 2) {
            // Two specifiers: %Y-%m
            return StrftimeFinding::CONFIDENCE_MEDIUM;
        } else {
            // Single specifier: %Y or just %
            return StrftimeFinding::CONFIDENCE_LOW;
        }
    }

    /**
     * Scan raw preference array (convenience method)
     *
     * @param array $prefsArray Raw preference array ($_prefs format)
     * @param array|null $prefNames Specific prefs to check (null = all)
     * @param array $options Scan options
     * @return StrftimeFinding[] Array of findings
     */
    public function scanArray(
        array $prefsArray,
        ?array $prefNames = null,
        array $options = []
    ): array {
        $prefsState = new PrefsState($prefsArray);
        return $this->scan($prefsState, $prefNames, $options);
    }
}
