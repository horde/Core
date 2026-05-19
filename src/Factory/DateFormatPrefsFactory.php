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

namespace Horde\Core\Factory;

use Horde\Core\Prefs\DateFormatPrefs;
use Horde_Injector;
use Horde\Injector\Injector;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory for DateFormatPrefs
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class DateFormatPrefsFactory
{
    public function create(Horde_Injector|Injector $injector): DateFormatPrefs
    {
        $logger = null;
        try {
            $logger = $injector->getInstance(LoggerInterface::class);
        } catch (\Throwable) {
            // Logger unavailable — proceed without
        }

        return new DateFormatPrefs(
            prefs: $injector->getInstance('Horde_Prefs'),
            locale: $GLOBALS['language'] ?? 'en_US',
            logger: $logger,
        );
    }
}
