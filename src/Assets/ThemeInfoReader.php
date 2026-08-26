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

namespace Horde\Core\Assets;

use Horde\Core\Factory\ThemeInfoReaderFactory;
use Horde\Injector\Attribute\Factory;

/**
 * Reads a theme's own asset declarations from its info.php.
 *
 * Themes may ship JavaScript alongside their CSS, images and sounds by
 * declaring plain file names in the theme directory's info.php:
 *
 *   $theme_scripts = array('theme.js');
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: ThemeInfoReaderFactory::class, method: 'create')]
interface ThemeInfoReader
{
    /**
     * Return the validated, readable script file names declared by a theme.
     *
     * Only plain *.js file names are returned; anything with a directory
     * separator, a '..' segment, a non-.js extension or a missing/unreadable
     * target file is dropped. A theme name that is not a plain directory name
     * yields an empty list.
     *
     * @param string $app    Application the theme belongs to.
     * @param string $theme  Theme name (as stored in user prefs/options).
     *
     * @return list<string> Validated script file names, in declaration order.
     */
    public function readScripts(string $app, string $theme): array;
}
