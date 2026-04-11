<?php

declare(strict_types=1);

namespace Horde\Core\Registry;

use Horde\Http\Uri;

/**
 * Default webroot URI.
 *
 * In a default deployment, below this uri there are
 *
 * $this . '/js' -> The JS root Uri
 * $this . '/static' -> The static files Uri
 * $this . '/horde' -> The Horde webroot Uri
 * $this . '/themes -> The Themes webroot Uri
 * $this . '/$app   -> The $app webroot Uri
 *
 * Any of this can be overridden by the deployment configuration.
 *
 * Also, an installation may be called by other host or FQDN names or even through IPs.
 *
 */
class DeploymentWebrootUri extends Uri {}
