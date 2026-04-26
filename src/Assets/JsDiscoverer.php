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

namespace Horde\Core\Assets;

use Horde\Core\Factory\JsDiscovererFactory;
use Horde\Injector\Attribute\Factory;

#[Factory(factory: JsDiscovererFactory::class, method: 'create')]
interface JsDiscoverer
{
    public function resolve(string $file, string $app = 'horde'): ?string;

    /** @return array<string, ?string> Map of file => uri (null if not found) */
    public function resolveMany(array $files, string $app = 'horde'): array;
}
