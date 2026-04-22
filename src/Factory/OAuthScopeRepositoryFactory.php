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
use Horde\Horde\Service\SqlOAuthScopeRepository;
use Horde\OAuth\Server\Repository\InMemory\InMemoryScopeRepository;
use Horde\OAuth\Server\Repository\ScopeRepository;
use Horde_Injector;
use Throwable;

class OAuthScopeRepositoryFactory
{
    public function create(Horde_Injector $injector): ScopeRepository
    {
        if (class_exists(SqlOAuthScopeRepository::class)) {
            try {
                $db = $injector->getInstance(Adapter::class);
                return new SqlOAuthScopeRepository($db);
            } catch (Throwable) {
                return new InMemoryScopeRepository();
            }
        }

        return new InMemoryScopeRepository();
    }
}
