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
 * Discovers locally installed package versions.
 *
 * Implementations may read from .horde.yml files, composer.lock,
 * or any other local source of truth.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
interface InstalledVersionSource
{
    /**
     * Get all locally installed package versions.
     *
     * @return array<string, VersionInfo> Keyed by Composer package name
     */
    public function getInstalledVersions(): array;
}
