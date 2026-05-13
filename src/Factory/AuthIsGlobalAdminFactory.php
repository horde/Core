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

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Middleware\AuthIsGlobalAdmin;
use Horde\Injector\Injector;

class AuthIsGlobalAdminFactory
{
    public function create(Injector $injector): AuthIsGlobalAdmin
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        $admins = $state->get('auth.admins', []);

        return new AuthIsGlobalAdmin(is_array($admins) ? $admins : []);
    }
}
