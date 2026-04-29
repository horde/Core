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

use Horde\Core\Factory\VersionServiceFactory;
use Horde\Injector\Attribute\Factory;
use Horde\Version\InvalidVersionException;
use Horde\Version\RelaxedSemanticVersion;

/**
 * Compares installed package versions against available upstream versions.
 *
 * Composes an InstalledVersionSource and an AvailableVersionSource to produce
 * VersionStatus results indicating which packages have updates available.
 *
 * This service is the primary entry point for version checking. It can be
 * injected into admin controllers, login tasks, CLI tools, and API providers.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: VersionServiceFactory::class, method: 'create')]
class VersionService
{
    /**
     * @param InstalledVersionSource  $installed Source for locally installed versions
     * @param AvailableVersionSource  $available Source for upstream available versions
     */
    public function __construct(
        private readonly InstalledVersionSource $installed,
        private readonly AvailableVersionSource $available,
    ) {}

    /**
     * Check all installed packages for available updates.
     *
     * @return array<string, VersionStatus> Keyed by Composer package name
     */
    public function checkAll(): array
    {
        $installedVersions = $this->installed->getInstalledVersions();
        $packageNames = array_keys($installedVersions);
        $availableVersions = $this->available->getAvailableVersions($packageNames);

        $results = [];
        foreach ($installedVersions as $name => $installedInfo) {
            $results[$name] = $this->compare($name, $installedInfo, $availableVersions[$name] ?? null);
        }

        return $results;
    }

    /**
     * Return version statuses using only cached data (no remote calls).
     *
     * Returns null if no cached results are available at all. Otherwise
     * returns the same format as checkAll() but only for packages that
     * have cached upstream data.
     *
     * @return array<string, VersionStatus>|null Null if cache is empty
     */
    public function checkAllCached(): ?array
    {
        $installedVersions = $this->installed->getInstalledVersions();
        $packageNames = array_keys($installedVersions);
        $availableVersions = $this->available->getAvailableVersions($packageNames, cacheOnly: true);

        if (empty($availableVersions)) {
            return null;
        }

        $results = [];
        foreach ($installedVersions as $name => $installedInfo) {
            $results[$name] = $this->compare($name, $installedInfo, $availableVersions[$name] ?? null);
        }

        return $results;
    }

    /**
     * Check a single package for available updates.
     *
     * @param string $packageName Composer package name (e.g. "horde/core")
     *
     * @return VersionStatus|null Null if the package is not installed
     */
    public function checkPackage(string $packageName): ?VersionStatus
    {
        $installedVersions = $this->installed->getInstalledVersions();
        if (!isset($installedVersions[$packageName])) {
            return null;
        }

        $availableInfo = $this->available->getPackageInfo($packageName);

        return $this->compare($packageName, $installedVersions[$packageName], $availableInfo);
    }

    /**
     * Quick check whether an update is available for a package.
     *
     * @param string $packageName Composer package name
     *
     * @return bool True if a newer version is available, false otherwise
     */
    public function isUpdateAvailable(string $packageName): bool
    {
        $status = $this->checkPackage($packageName);

        return $status !== null && $status->status === UpdateAvailability::UpdateAvailable;
    }

    /**
     * Compare installed vs available versions for a single package.
     *
     * Uses Horde\Version\RelaxedSemanticVersion for proper semver comparison
     * that handles differences like "3.1.0" vs "3.1.0.0" correctly.
     *
     * @param string           $packageName   Package name
     * @param VersionInfo      $installed     Installed version info
     * @param VersionInfo|null $available     Available version info, null if lookup failed
     *
     * @return VersionStatus Comparison result
     */
    private function compare(
        string $packageName,
        VersionInfo $installed,
        ?VersionInfo $available,
    ): VersionStatus {
        if ($available === null) {
            return new VersionStatus(
                packageName: $packageName,
                installedVersion: $installed->version,
                availableVersion: '',
                status: UpdateAvailability::Unknown,
            );
        }

        try {
            $installedVer = new RelaxedSemanticVersion($installed->version);
            $availableVer = new RelaxedSemanticVersion($available->version);
            $status = $installedVer->isLessThan($availableVer)
                ? UpdateAvailability::UpdateAvailable
                : UpdateAvailability::UpToDate;
        } catch (InvalidVersionException) {
            $status = UpdateAvailability::Unknown;
        }

        return new VersionStatus(
            packageName: $packageName,
            installedVersion: $installed->version,
            availableVersion: $available->version,
            status: $status,
            url: $available->url,
        );
    }
}
