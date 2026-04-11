<?php

declare(strict_types=1);

namespace Horde\Core\Registry;

use Horde\Http\Uri;

/**
 * An application's themes URI.
 *
 * By default, this is $deploymentWebrootUri . '/themes/$application/';
 *
 */
class ApplicationThemesUri extends Uri {}
