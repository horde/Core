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

use Horde\Core\Assets\AssetFilesystem;
use Horde\Core\Assets\PhpThemeInfoReader;
use Horde\Core\Assets\ThemeInfoReader;
use Horde\Core\Path\PathBuilderInterface;
use Horde\Injector\Injector;

class ThemeInfoReaderFactory
{
    public function create(Injector $injector): ThemeInfoReader
    {
        return new PhpThemeInfoReader(
            $injector->getInstance(PathBuilderInterface::class),
            $injector->getInstance(AssetFilesystem::class),
        );
    }
}
