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

use Horde\Core\Assets\PrefsThemeResolver;
use Horde\Core\Assets\ThemeResolver;
use Horde\Core\Service\PrefsService;
use Horde\Injector\Injector;

class ThemeResolverFactory
{
    public function create(Injector $injector): ThemeResolver
    {
        $prefsService = $injector->getInstance(PrefsService::class);

        return new PrefsThemeResolver($prefsService);
    }
}
