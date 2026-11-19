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

use Horde\Core\Config\PrefsConfigLoader;
use Horde\Core\Config\Vhost;
use Horde\Injector\Injector;

/**
 * Factory for PrefsConfigLoader
 *
 * Supplies the config base (HORDE_CONFIG_BASE) and vendor base (HORDE_BASE)
 * that PrefsConfigLoader needs to locate each app's prefs.php across the
 * vendor, base, snippet, local and vhost layers.
 *
 * Mirrors BackendConfigLoaderFactory: vendorBase must be the directory
 * containing each app package (vendor/horde/), not the horde package itself.
 * HORDE_BASE points at vendor/horde/horde, so dirname(HORDE_BASE) is correct.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class PrefsConfigLoaderFactory
{
    public function create(Injector $injector): PrefsConfigLoader
    {
        $configBase = defined('HORDE_CONFIG_BASE') ? HORDE_CONFIG_BASE : '/etc/horde';

        // PrefsConfigLoader builds "$vendorBase/$app/config/", so vendorBase
        // must be the directory CONTAINING each app package (vendor/horde/),
        // not the horde package itself. HORDE_BASE points at vendor/horde/horde,
        // so its parent is the correct base.
        $vendorBase = defined('HORDE_BASE')
            ? dirname(HORDE_BASE)
            : __DIR__ . '/../../../..';

        return new PrefsConfigLoader($configBase, $vendorBase, new Vhost());
    }
}
