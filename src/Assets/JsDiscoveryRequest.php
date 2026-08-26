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

/**
 * Describes a theme-aware JavaScript discovery request.
 *
 * Parallel to {@see CssDiscoveryRequest}. When $files is empty the discoverer
 * derives the file list from the theme's own declarations (info.php
 * $theme_scripts).
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class JsDiscoveryRequest
{
    /** @param list<string> $files */
    public function __construct(
        public readonly array $files = [],
        public readonly string $app = 'horde',
        public readonly string $theme = 'default',
    ) {}
}
