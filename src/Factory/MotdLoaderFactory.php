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

use Horde\Core\Config\MotdLoader;
use Horde\Core\Config\Vhost;
use Horde\Injector\Injector;

/**
 * Factory for MotdLoader
 *
 * Supplies the config base (HORDE_CONFIG_BASE) and vendor base (HORDE_BASE)
 * that MotdLoader needs to locate motd.php across the vendor, base, snippet,
 * local, and vhost layers. Mirrors BackendConfigLoaderFactory.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class MotdLoaderFactory
{
    public function create(Injector $injector): MotdLoader
    {
        $configBase = defined('HORDE_CONFIG_BASE') ? HORDE_CONFIG_BASE : '/etc/horde';

        // MotdLoader builds "$vendorBase/$app/config/", so vendorBase must be
        // the directory CONTAINING each app package (vendor/horde/), not the
        // horde package itself. HORDE_BASE points at vendor/horde/horde, so its
        // parent is the correct base.
        $vendorBase = defined('HORDE_BASE')
            ? dirname(HORDE_BASE)
            : __DIR__ . '/../../../..';

        return new MotdLoader($configBase, $vendorBase, new Vhost());
    }
}
