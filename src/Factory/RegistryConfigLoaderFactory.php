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

use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Config\Vhost;
use Horde_Injector;

/**
 * Factory for RegistryConfigLoader
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class RegistryConfigLoaderFactory
{
    public function create(Horde_Injector $injector): RegistryConfigLoader
    {
        $configBase = defined('HORDE_CONFIG_BASE') ? HORDE_CONFIG_BASE : '/etc/horde';
        $vendorBase = defined('HORDE_BASE') ? HORDE_BASE : __DIR__ . '/../../../';

        return new RegistryConfigLoader($configBase, $vendorBase, new Vhost());
    }
}
