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

use Horde\Core\Auth\Storage\FileAuthLinkRepository;
use Horde\Core\Auth\Storage\InMemoryAuthLinkRepository;
use Horde\Core\Config\ConfigLoader;
use Horde\Db\Adapter;
use Horde\Horde\Service\AuthLinkRepository;
use Horde\Horde\Service\SqlAuthLinkRepository;
use Horde\Injector\Injector;
use Throwable;

/**
 * Factory for AuthLinkRepository.
 *
 * Fallback chain: SQL → file-based → in-memory no-op.
 * This ensures auth link resolution works even in SQL-free deployments.
 */
class AuthLinkRepositoryFactory
{
    public function create(Injector $injector): AuthLinkRepository
    {
        try {
            $db = $injector->getInstance(Adapter::class);
            return new SqlAuthLinkRepository($db);
        } catch (Throwable) {
            // SQL not available, try file-based
        }

        try {
            $loader = $injector->getInstance(ConfigLoader::class);
            $state = $loader->load('horde');
            $filePath = $state->get('auth.authlink_file', '');

            if ($filePath === '') {
                $varDir = $state->get('vhosts.var_dir', '/var/lib/horde');
                $filePath = $varDir . '/auth_links.json';
            }

            return new FileAuthLinkRepository($filePath);
        } catch (Throwable) {
            // Config not available, fall through to in-memory
        }

        return new InMemoryAuthLinkRepository();
    }
}
