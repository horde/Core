<?php

declare(strict_types=1);

namespace Horde\Core\Assets;

/**
 * Filesystem abstraction for ResponsiveAssets
 *
 * Allows mocking file system operations in tests.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
interface ResponsiveAssetsFilesystem
{
    /**
     * Check if file exists
     *
     * @param string $path File path
     * @return bool True if file exists
     */
    public function fileExists(string $path): bool;
}
