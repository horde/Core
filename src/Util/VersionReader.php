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

namespace Horde\Core\Util;

use Exception;
use Horde\HordeYmlFile\HordeYmlFile;

/**
 * Utility for reading version information from .horde.yml files.
 *
 * Provides consistent interface for extracting version data across
 * the codebase without duplicating HordeYmlFile instantiation logic.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class VersionReader
{
    /**
     * Read version from a .horde.yml file path.
     *
     * @param string $hordeYmlPath Full path to .horde.yml file
     *
     * @return string Version string, or empty string if unable to read
     */
    public static function readVersion(string $hordeYmlPath): string
    {
        if (!file_exists($hordeYmlPath)) {
            return '';
        }

        try {
            $hordeYml = new HordeYmlFile($hordeYmlPath);
            return $hordeYml->getReleaseVersion();
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Read name and version from a .horde.yml file path.
     *
     * @param string $hordeYmlPath Full path to .horde.yml file
     *
     * @return array{name: string, version: string} Name and version, empty strings if unable to read
     */
    public static function readNameAndVersion(string $hordeYmlPath): array
    {
        if (!file_exists($hordeYmlPath)) {
            return ['name' => '', 'version' => ''];
        }

        try {
            $hordeYml = new HordeYmlFile($hordeYmlPath);
            return [
                'name' => $hordeYml->getName(),
                'version' => $hordeYml->getReleaseVersion(),
            ];
        } catch (Exception $e) {
            return ['name' => '', 'version' => ''];
        }
    }

    /**
     * Read version from application fileroot directory.
     *
     * Automatically appends '/.horde.yml' to the provided directory path.
     *
     * @param string $fileroot Application fileroot directory
     *
     * @return string Version string, or empty string if unable to read
     */
    public static function readVersionFromFileroot(string $fileroot): string
    {
        return self::readVersion($fileroot . '/.horde.yml');
    }

    /**
     * Read name and version from application fileroot directory.
     *
     * Automatically appends '/.horde.yml' to the provided directory path.
     *
     * @param string $fileroot Application fileroot directory
     *
     * @return array{name: string, version: string} Name and version, empty strings if unable to read
     */
    public static function readNameAndVersionFromFileroot(string $fileroot): array
    {
        return self::readNameAndVersion($fileroot . '/.horde.yml');
    }
}
