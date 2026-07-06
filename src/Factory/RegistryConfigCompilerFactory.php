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

use Horde\Core\Config\RegistryConfigCompiler;
use Horde\Injector\Injector;

/**
 * Factory for RegistryConfigCompiler
 *
 * Mirrors RegistryConfigLoaderFactory. The compiler needs the same
 * two paths the loader needs (config base and vendor base). The
 * $vhosts argument stays null so the compiler auto-discovers vhost
 * files from disk. Callers who need explicit vhost control (CI,
 * tests, deployment tooling) construct the compiler directly rather
 * than through the injector.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class RegistryConfigCompilerFactory
{
    public function create(Injector $injector): RegistryConfigCompiler
    {
        $configBase = defined('HORDE_CONFIG_BASE') ? HORDE_CONFIG_BASE : '/etc/horde';
        $vendorBase = defined('HORDE_BASE') ? HORDE_BASE : __DIR__ . '/../../../';

        return new RegistryConfigCompiler($configBase, $vendorBase);
    }
}
