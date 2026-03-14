<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\Vhost;
use Horde_Injector;

/**
 * Factory for ConfigLoader service
 *
 * Creates the global multi-app configuration loader.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class ConfigLoaderFactory
{
    /**
     * Create ConfigLoader instance
     *
     * @param Horde_Injector $injector Dependency injector
     * @return ConfigLoader Global config loader for all apps
     */
    public function create(Horde_Injector $injector): ConfigLoader
    {
        return new ConfigLoader(HORDE_CONFIG_BASE, new Vhost());
    }
}
