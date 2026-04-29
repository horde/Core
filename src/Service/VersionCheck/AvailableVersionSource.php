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
 * Looks up available (upstream) package versions from a remote source.
 *
 * Implementations may query Packagist, a custom horde.org endpoint,
 * or any other package registry.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
interface AvailableVersionSource
{
    /**
     * Look up the latest available version for each given package.
     *
     * @param array<string> $packageNames Composer package names to check
     * @param bool          $cacheOnly    If true, only return cached results (no remote calls)
     *
     * @return array<string, VersionInfo> Keyed by package name; missing packages are omitted
     */
    public function getAvailableVersions(array $packageNames, bool $cacheOnly = false): array;

    /**
     * Look up version info for a single package.
     *
     * @param string $packageName Composer package name (e.g. "horde/core")
     * @param bool   $cacheOnly   If true, only return cached result (no remote call)
     *
     * @return VersionInfo|null Null if the package was not found or the lookup failed
     */
    public function getPackageInfo(string $packageName, bool $cacheOnly = false): ?VersionInfo;
}
