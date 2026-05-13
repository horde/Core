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
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use Horde\Core\Editor\TinymcePageBinder;
use Horde\Editor\Tinymce;
use Horde\Injector\Injector;

/**
 * Factory for TinymcePageBinder.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class TinymcePageBinderFactory
{
    public function create(Injector $injector): TinymcePageBinder
    {
        return new TinymcePageBinder(
            $injector->getInstance(Tinymce::class),
        );
    }
}
