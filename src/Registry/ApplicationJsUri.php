<?php

declare(strict_types=1);

namespace Horde\Core\Registry;

use Horde\Http\Uri;

/**
 * An application's javascript URI.
 *
 * By default, this is $deploymentWebrootUri . '/js/$application/';
 * Shared assets are retrieved from the base application, i.e. /js/horde/
 * In Horde 5 and older, the canonical URI was /horde/$application/js/ and horde/js
 */
class ApplicationJsUri extends Uri {}
