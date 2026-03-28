<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Config\Legacy;

use Horde\Core\Config\ConfigMetadataProvider;

/**
 * Adapter for legacy Horde_Config format.
 *
 * Bridges between the new metadata system and legacy Horde_Config
 * array format used by existing configuration forms.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class LegacyConfigAdapter
{
    public function __construct(
        private readonly ConfigMetadataProvider $provider,
    ) {}

    /**
     * Convert driver metadata to legacy configSQL() format.
     *
     * @param string $type Driver type (e.g., 'sql')
     *
     * @return array<string, mixed> Legacy format with 'switch' array
     */
    public function toConfigSQL(string $type = 'sql'): array
    {
        $drivers = $this->provider->getAvailableDrivers($type);
        $legacy = ['switch' => []];

        foreach ($drivers as $name => $description) {
            $legacy['switch'][$name] = $this->provider->toLegacyFormat($type, $name);
        }

        return $legacy;
    }

    /**
     * Convert driver metadata to legacy configNoSQL() format.
     *
     * @param string $type Driver type (e.g., 'nosql')
     *
     * @return array<string, mixed> Legacy format
     */
    public function toConfigNoSQL(string $type = 'nosql'): array
    {
        return $this->toConfigSQL($type);
    }

    /**
     * Convert driver metadata to legacy configLDAP() format.
     *
     * @return array<string, mixed> Legacy format
     */
    public function toConfigLDAP(): array
    {
        return $this->toConfigSQL('ldap');
    }

    /**
     * Convert driver metadata to legacy configVFS() format.
     *
     * @return array<string, mixed> Legacy format
     */
    public function toConfigVFS(): array
    {
        return $this->toConfigSQL('vfs');
    }
}
