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

namespace Horde\Core\Service\VersionCheck;

/**
 * Immutable value object representing the version comparison result
 * for a single package.
 *
 * Combines the installed version, the latest available version, and a
 * status enum indicating whether an update is available.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class VersionStatus
{
    /**
     * @param string              $packageName      Composer package name
     * @param string              $installedVersion Currently installed version (normalized)
     * @param string              $availableVersion Latest available version (normalized), empty if unknown
     * @param UpdateAvailability  $status           Comparison result
     * @param string              $url              URL for download or package info, empty if unknown
     */
    public function __construct(
        public readonly string $packageName,
        public readonly string $installedVersion,
        public readonly string $availableVersion,
        public readonly UpdateAvailability $status,
        public readonly string $url = '',
    ) {}
}
