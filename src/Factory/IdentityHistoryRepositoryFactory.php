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
use Horde\Horde\Service\SqlIdentityHistoryRepository;
use Horde\Identity\IdentityHistoryRepository;
use Horde\Identity\InMemoryIdentityHistoryRepository;
use Horde_Injector;
use Throwable;

/**
 * Factory for IdentityHistoryRepository.
 *
 * Tries SqlIdentityHistoryRepository when available, falls back to InMemoryIdentityHistoryRepository.
 */
class IdentityHistoryRepositoryFactory
{
    public function create(Horde_Injector $injector): IdentityHistoryRepository
    {
        if (class_exists(SqlIdentityHistoryRepository::class)) {
            try {
                $db = $injector->getInstance(Adapter::class);
                return new SqlIdentityHistoryRepository($db);
            } catch (Throwable) {
                return new InMemoryIdentityHistoryRepository();
            }
        }

        return new InMemoryIdentityHistoryRepository();
    }
}
