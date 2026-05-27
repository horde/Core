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
 * Utility for IANA default port handling.
 */
class PortUtil
{
    private const SCHEME_DEFAULTS = [
        'http' => 80,
        'https' => 443,
    ];

    /**
     * Whether a port is the IANA default for the given scheme.
     *
     * Returns true when the port can be omitted from a URI without
     * changing its meaning (RFC 3986 section 3.2.3).
     */
    public static function isIanaDefault(string $scheme, int|string|null $port): bool
    {
        if ($port === null || $port === '') {
            return true;
        }
        $default = self::SCHEME_DEFAULTS[strtolower($scheme)] ?? null;
        return $default !== null && (int) $port === $default;
    }

    /**
     * Strip the port from a host:port string when it is the IANA default for the scheme.
     */
    public static function stripDefaultPort(string $scheme, string $host): string
    {
        if (!str_contains($host, ':')) {
            return $host;
        }
        $lastColon = strrpos($host, ':');
        $port = substr($host, $lastColon + 1);
        if (self::isIanaDefault($scheme, $port)) {
            return substr($host, 0, $lastColon);
        }
        return $host;
    }
}
