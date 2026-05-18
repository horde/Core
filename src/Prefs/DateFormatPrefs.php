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

use Horde\Date\Format;
use Horde_Prefs;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Date format preferences decorator
 *
 * Wraps date format preference access with automatic strftime-to-ICU
 * conversion. On first access of a legacy strftime value:
 * 1. Detects strftime format
 * 2. Logs the conversion
 * 3. Converts to ICU pattern
 * 4. Writes back the converted value (failsafe, noop if impossible)
 * 5. Returns the ICU pattern
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class DateFormatPrefs
{
    private const DATE_FORMAT_KEYS = [
        'date_format',
        'date_format_mini',
        'time_format',
        'time_format_mini',
    ];

    public function __construct(
        private Horde_Prefs $prefs,
        private string $locale = 'en_US',
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Get a date format preference as an ICU pattern.
     *
     * If the stored value is strftime, converts it to ICU, writes back
     * the converted value (failsafe), logs the conversion, and returns
     * the ICU pattern. If already ICU, returns as-is.
     */
    public function getDateFormat(string $key): string
    {
        $value = $this->prefs->getValue($key);
        if ($value === null) {
            return '';
        }

        if (!Format::isStrftimeFormat($value)) {
            return $value;
        }

        $icu = Format::strftimeToIcu($value, $this->locale);

        $this->logger?->notice(
            "DateFormatPrefs: converting legacy strftime pref '{$key}': '{$value}' → '{$icu}'"
        );

        if (!$this->prefs->isLocked($key)) {
            try {
                $this->prefs->setValue($key, $icu);
            } catch (Throwable) {
                // Noop — read-only backend or other error
            }
        }

        return $icu;
    }

    /**
     * Get the short time format ICU pattern based on twentyFour pref.
     *
     * Replaces the common pattern:
     *   $prefs->getValue('twentyFour') ? '%R' : '%I:%M%p'
     */
    public function getTimeFormatShort(): string
    {
        return $this->prefs->getValue('twentyFour') ? 'HH:mm' : 'h:mm a';
    }

    /**
     * Get the time format with seconds based on twentyFour pref.
     *
     * Replaces the common pattern:
     *   $prefs->getValue('twentyFour') ? '%H:%M:%S' : '%I:%M:%S %p'
     */
    public function getTimeFormatFull(): string
    {
        return $this->prefs->getValue('twentyFour') ? 'HH:mm:ss' : 'h:mm:ss a';
    }

    /**
     * Check if a given pref key is a date format key handled by this class.
     */
    public function isDateFormatKey(string $key): bool
    {
        return in_array($key, self::DATE_FORMAT_KEYS, true);
    }
}
