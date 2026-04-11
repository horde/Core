<?php

declare(strict_types=1);

namespace Horde\Core\Config;

use Horde\Core\Directory;

/**
 * An application's exposed directory on the filesystem. Usually $deployment/web/themes/$application/.
 *
 * Themes put their global stylesheets and assets in $this . /horde/$theme/ and their per-application
 * stylesheets and assets in $this . /$application/$theme/.
 */
class ApplicationWebThemesDirectory extends Directory {}
