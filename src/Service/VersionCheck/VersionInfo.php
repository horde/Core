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
 * Immutable value object representing version information for a single package.
 *
 * Used by both installed and available version sources to convey version data
 * in a consistent format.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class VersionInfo
{
    /**
     * @param string $packageName       Composer package name (e.g. "horde/core")
     * @param string $version           Display version string (e.g. "v3.0.0beta23")
     * @param string $versionNormalized Normalized version for comparison (e.g. "3.0.0.0-beta23")
     * @param string $releaseDate       ISO 8601 release date, empty if unknown
     * @param string $url               URL for download or package info page, empty if unknown
     */
    public function __construct(
        public readonly string $packageName,
        public readonly string $version,
        public readonly string $versionNormalized,
        public readonly string $releaseDate = '',
        public readonly string $url = '',
    ) {}
}
