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

namespace Horde\Core\Uri;

/**
 * Provides named route path generation.
 *
 * Implemented by RuntimeRoutesProvider (development mode, live Route objects)
 * and in the future by a compiled routes adapter (production mode, opcached
 * arrays — currently named CompiledGenerator in horde/Routes).
 *
 * RouteUrlWriter consumes this interface to generate full URLs without
 * coupling to the runtime-vs-compiled distinction.
 */
interface RoutesProvider
{
    /**
     * Generate a URL path for a named route.
     *
     * @param string $routeName Named route identifier from config/routes.php
     * @param array<string, string> $params Route parameters to fill placeholders
     * @return string|null Generated path (including prefix) or null if route not found
     */
    public function generateNamedPath(string $routeName, array $params = []): ?string;
}
