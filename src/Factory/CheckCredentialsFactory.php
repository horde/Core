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
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use Horde\Core\Middleware\CheckCredentials;
use Horde_Core_Factory_Injector as InjectorFactory;
use Horde\Injector\Injector;

class CheckCredentialsFactory extends InjectorFactory
{
    public function create(Injector $injector): CheckCredentials
    {
        $driver = $injector->getInstance('Horde_Core_Factory_Auth')->create();

        return new CheckCredentials($driver);
    }
}
