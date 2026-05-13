<?php

declare(strict_types=1);

/**
 * Injector-compatible factory for Horde_Auth_Base.
 *
 * Delegates to the legacy Horde_Core_Factory_Auth::create() (no-arg form)
 * which returns the horde app's auth driver (a Horde_Auth_Base subclass).
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

namespace Horde\Core\Factory;

use Horde_Auth_Base;
use Horde_Core_Factory_Injector;
use Horde\Injector\Injector;

class AuthBaseFactory extends Horde_Core_Factory_Injector
{
    public function create(Injector $injector): Horde_Auth_Base
    {
        return $injector->getInstance('Horde_Core_Factory_Auth')->create();
    }
}
