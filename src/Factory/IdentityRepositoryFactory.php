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
use Horde\Horde\Service\SqlIdentityRepository;
use Horde\Identity\IdentityRepository;
use Horde\Identity\InMemoryIdentityRepository;
use Horde\Injector\Injector;
use Throwable;

/**
 * Factory for IdentityRepository.
 *
 * Tries SqlIdentityRepository when available, falls back to InMemoryIdentityRepository.
 */
class IdentityRepositoryFactory
{
    public function create(Injector $injector): IdentityRepository
    {
        if (class_exists(SqlIdentityRepository::class)) {
            try {
                $db = $injector->getInstance(Adapter::class);
                return new SqlIdentityRepository($db);
            } catch (Throwable) {
                return new InMemoryIdentityRepository();
            }
        }

        return new InMemoryIdentityRepository();
    }
}
