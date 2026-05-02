<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license http://www.horde.org/licenses/lgpl21 LGPL-2.1
 */

namespace Horde\Core\Factory;

use Horde\Core\Auth\AuthService;
use Horde\Core\Middleware\CheckCredentials;
use Horde_Injector;

class CheckCredentialsFactory
{
    public function create(Horde_Injector $injector): CheckCredentials
    {
        $authService = $injector->getInstance(AuthService::class);

        return new CheckCredentials($authService);
    }
}
