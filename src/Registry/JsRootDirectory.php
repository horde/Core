<?php

declare(strict_types=1);

namespace Horde\Core\Config;

use Horde\Core\Directory;

/*
 * The root directory for JavaScript files exposed to the web server.
 *
 * Canonically this is the $deployment/web/js/ directory.
 */
class JsRootDirectory extends Directory {}
