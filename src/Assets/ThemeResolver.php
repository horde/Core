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

use Horde\Core\Factory\ThemeResolverFactory;
use Horde\Identity\Identity;
use Horde\Injector\Attribute\Factory;

#[Factory(factory: ThemeResolverFactory::class, method: 'create')]
interface ThemeResolver
{
    /**
     * @param Identity|string $identity Identity object or user ID string
     * @param string|null $authUid Fallback auth user ID for pref lookup
     * @param string|null $app Prefs scope (defaults to 'horde')
     */
    public function resolve(Identity|string $identity, ?string $authUid = null, ?string $app = null): string;
}
