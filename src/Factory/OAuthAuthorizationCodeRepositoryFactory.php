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

use Horde\Db\Adapter;
use Horde\Horde\Service\SqlOAuthAuthorizationCodeRepository;
use Horde\OAuth\Server\Repository\AuthorizationCodeRepository;
use Horde\OAuth\Server\Repository\InMemory\InMemoryAuthorizationCodeRepository;
use Horde\Injector\Injector;
use Throwable;

class OAuthAuthorizationCodeRepositoryFactory
{
    public function create(Injector $injector): AuthorizationCodeRepository
    {
        if (class_exists(SqlOAuthAuthorizationCodeRepository::class)) {
            try {
                $db = $injector->getInstance(Adapter::class);
                return new SqlOAuthAuthorizationCodeRepository($db);
            } catch (Throwable) {
                return new InMemoryAuthorizationCodeRepository();
            }
        }

        return new InMemoryAuthorizationCodeRepository();
    }
}
