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

namespace Horde\Core\Config;

/**
 * VHost detection for config loading
 *
 * Encapsulates logic for determining the current virtual host name
 * for loading vhost-specific configuration files.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class Vhost
{
    private ?string $hostname;

    /**
     * Constructor
     *
     * @param string|null $hostname Hostname (null = auto-detect from $_SERVER)
     */
    public function __construct(?string $hostname = null)
    {
        if ($hostname === null) {
            // Auto-detect from $_SERVER
            $this->hostname = $_SERVER['SERVER_NAME']
                ?? $_SERVER['HTTP_HOST']
                ?? null;
        } else {
            $this->hostname = $hostname;
        }
    }

    /**
     * Get hostname
     *
     * @return string|null Hostname or null if not available
     */
    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    /**
     * Check if vhost is available
     *
     * @return bool True if hostname is available
     */
    public function isAvailable(): bool
    {
        return $this->hostname !== null;
    }

    /**
     * Get vhost-specific filename
     *
     * Examples:
     * - conf.php → conf-example.com.php
     * - backends.local.php → backends-example.com.local.php
     * - prefs.php → prefs-example.com.php
     *
     * @param string $basename Base filename (e.g., 'conf.php')
     * @return string|null Vhost filename or null if vhost not available
     */
    public function getVhostFilename(string $basename): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        // Handle compound extensions like 'backends.local.php'
        // Pattern: filename.ext1.ext2 → filename-vhost.ext1.ext2
        $parts = explode('.', $basename);

        if (count($parts) < 2) {
            // No extension
            return $basename . '-' . $this->hostname;
        }

        // Insert vhost after first part (filename)
        $filename = array_shift($parts);
        $extensions = implode('.', $parts);

        return $filename . '-' . $this->hostname . '.' . $extensions;
    }

    /**
     * Create Vhost from string (for union type support)
     *
     * @param Vhost|string $vhost Vhost object or hostname string
     * @return Vhost
     */
    public static function from(Vhost|string $vhost): Vhost
    {
        if ($vhost instanceof Vhost) {
            return $vhost;
        }
        return new Vhost($vhost);
    }
}
