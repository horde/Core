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
use Horde\Horde\Service\AuthLinkRepository;
use Horde\Horde\Service\SqlAuthLinkRepository;
use Horde_Injector;

/**
 * Factory for AuthLinkRepository.
 *
 * Creates SqlAuthLinkRepository backed by the Horde DB adapter.
 */
class AuthLinkRepositoryFactory
{
    public function create(Horde_Injector $injector): AuthLinkRepository
    {
        $db = $injector->getInstance(Adapter::class);
        return new SqlAuthLinkRepository($db);
    }
}
