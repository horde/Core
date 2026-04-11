<?php

declare(strict_types=1);

namespace Horde\Core\Registry;

use Horde\Http\Uri;

/**
 * An application's webroot URI.
 *
 * By default, this is $deploymentWebrootUri . '/$application/';
 *
 */
class ApplicationWebrootUri extends Uri {}
