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

namespace Horde\Core\Factory;

use Horde\Core\Config\BackendConfigLoader;
use Horde\Core\Config\Vhost;
use Horde\Injector\Injector;

/**
 * Factory for BackendConfigLoader
 *
 * Supplies the config base (HORDE_CONFIG_BASE) and vendor base (HORDE_BASE)
 * that BackendConfigLoader needs to locate each app's backends.php across the
 * vendor, base, snippet, local, and vhost layers. Mirrors
 * RegistryConfigLoaderFactory.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class BackendConfigLoaderFactory
{
    public function create(Injector $injector): BackendConfigLoader
    {
        $configBase = defined('HORDE_CONFIG_BASE') ? HORDE_CONFIG_BASE : '/etc/horde';

        // BackendConfigLoader builds "$vendorBase/$app/config/", so vendorBase
        // must be the directory CONTAINING each app package (vendor/horde/),
        // not the horde package itself. HORDE_BASE points at vendor/horde/horde,
        // so its parent is the correct base.
        $vendorBase = defined('HORDE_BASE')
            ? dirname(HORDE_BASE)
            : __DIR__ . '/../../../..';

        return new BackendConfigLoader($configBase, $vendorBase, new Vhost());
    }
}
