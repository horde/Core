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
use Horde\Horde\Service\SqlOAuthRefreshTokenRepository;
use Horde\OAuth\Server\Repository\InMemory\InMemoryRefreshTokenRepository;
use Horde\OAuth\Server\Repository\RefreshTokenRepository;
use Horde\Injector\Injector;
use Throwable;

class OAuthRefreshTokenRepositoryFactory
{
    public function create(Injector $injector): RefreshTokenRepository
    {
        if (class_exists(SqlOAuthRefreshTokenRepository::class)) {
            try {
                $db = $injector->getInstance(Adapter::class);
                return new SqlOAuthRefreshTokenRepository($db);
            } catch (Throwable) {
                return new InMemoryRefreshTokenRepository();
            }
        }

        return new InMemoryRefreshTokenRepository();
    }
}
