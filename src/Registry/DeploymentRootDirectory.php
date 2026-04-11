<?php

declare(strict_types=1);

namespace Horde\Core\Registry;

use Horde\Core\Directory;

/**
 * DeploymentRootDirectory - the home of the root composer.json and composer.lock files.
 *
 * Canonically the autoloader is $this  . '/vendor/autoload.php'.
 */
class DeploymentRootDirectory extends Directory {}
