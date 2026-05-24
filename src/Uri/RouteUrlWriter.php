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
 * Generates URLs from named routes.
 *
 * Consumes a RoutesProvider for path generation and adds scheme/host
 * qualification from the current request environ. Controllers should
 * type-hint this class for URL generation needs.
 */
class RouteUrlWriter
{
    /**
     * @param RoutesProvider $provider Route path generator
     * @param array<string, string> $environ Server environ (HTTP_HOST, SERVER_NAME, HTTPS)
     * @param string $webroot Application webroot path
     */
    public function __construct(
        private readonly RoutesProvider $provider,
        private readonly array $environ,
        private readonly string $webroot,
    ) {}

    /**
     * Generate a relative URL path for a named route.
     */
    public function urlFor(string $routeName, array $params = []): ?string
    {
        return $this->provider->generateNamedPath($routeName, $params);
    }

    /**
     * Generate a fully qualified (absolute) URL for a named route.
     */
    public function absoluteUrlFor(string $routeName, array $params = []): ?string
    {
        $path = $this->provider->generateNamedPath($routeName, $params);
        if ($path === null) {
            return null;
        }

        $host = $this->environ['HTTP_HOST']
            ?? $this->environ['SERVER_NAME']
            ?? 'localhost';

        $scheme = (!empty($this->environ['HTTPS']) && $this->environ['HTTPS'] !== 'off')
            ? 'https'
            : 'http';

        return $scheme . '://' . $host . $path;
    }

    /**
     * Get the configured webroot path.
     */
    public function getWebroot(): string
    {
        return $this->webroot;
    }
}
