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

use Exception;
use Horde\Core\Service\ApplicationService;
use Horde\HordeYmlFile\HordeYmlFile;

/**
 * Discovers installed package versions by reading .horde.yml files.
 *
 * This is one possible implementation of InstalledVersionSource. It uses
 * ApplicationService to enumerate installed applications and their fileroots,
 * then reads each .horde.yml via the HordeYmlFile library to extract the
 * Composer package name and release version.
 *
 * Alternative implementations could read from composer.lock, a database
 * cache, or any other local source of truth.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class HordeYmlInstalledSource implements InstalledVersionSource
{
    /**
     * @param ApplicationService $appService Provides list of installed apps with fileroots
     */
    public function __construct(
        private readonly ApplicationService $appService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getInstalledVersions(): array
    {
        $versions = [];

        foreach ($this->appService->listApplications() as $appInfo) {
            $fileroot = $appInfo['fileroot'] ?? '';
            if ($fileroot === '') {
                continue;
            }

            $hordeYmlPath = $fileroot . '/.horde.yml';
            if (!file_exists($hordeYmlPath)) {
                continue;
            }

            try {
                $hordeYml = new HordeYmlFile($hordeYmlPath);
                $composerName = $hordeYml->getComposerName();
                $releaseVersion = $hordeYml->getReleaseVersion();

                if ($composerName === '' || $releaseVersion === '') {
                    continue;
                }

                $versions[$composerName] = new VersionInfo(
                    packageName: $composerName,
                    version: $releaseVersion,
                    versionNormalized: $this->normalizeVersion($releaseVersion),
                );
            } catch (Exception) {
                // HordeYmlFile throws SPL exceptions (RuntimeException,
                // InvalidArgumentException) — catch at the boundary to
                // gracefully skip unparseable files without coupling to
                // the library's internal exception hierarchy.
                continue;
            }
        }

        return $versions;
    }

    /**
     * Normalize a version string for comparison.
     *
     * Strips leading "v" and converts common Horde patterns like
     * "3.0.0beta23" to "3.0.0.0-beta23" for version_compare() compatibility.
     *
     * @param string $version Raw version string
     *
     * @return string Normalized version
     */
    private function normalizeVersion(string $version): string
    {
        $version = ltrim($version, 'vV');

        // Convert "3.0.0beta23" to "3.0.0-beta23"
        $version = (string) preg_replace(
            '/^(\d+\.\d+\.\d+)(alpha|beta|RC)(\d*)$/i',
            '$1-$2$3',
            $version,
        );

        return $version;
    }
}
