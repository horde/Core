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

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Middleware\ErrorFilter;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Injector\Injector;
use Exception;

class ErrorFilterFactory
{
    public function create(Injector $injector): ErrorFilter
    {
        $admins = [];
        try {
            $configLoader = $injector->getInstance(ConfigLoader::class);
            $conf = $configLoader->load('horde', 'conf.php');
            $admins = $conf->get('auth.admins', []);
            if (!is_array($admins)) {
                $admins = [];
            }
        } catch (Exception $e) {
            // Config not available — no admins list, safe error display only
        }

        return new ErrorFilter(
            $admins,
            new ResponseFactory(),
            new StreamFactory(),
        );
    }
}
