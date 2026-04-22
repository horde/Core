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

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Horde;
use Horde\Core\Service\HordeDbService;
use Horde\OAuth\Client\FileOAuthFlowStore;
use Horde\OAuth\Client\OAuthFlowStore;
use Horde\Horde\Service\SqlOAuthFlowStore;
use Horde_Injector;

class OAuthFlowStoreFactory
{
    public function create(Horde_Injector $injector): OAuthFlowStore
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        $driver = strtolower((string) $state->get('oauth_login.flow_store_driver', 'File'));
        $params = $state->get('oauth_login.flow_store_params', []);

        return match ($driver) {
            'sql' => $this->createSqlStore($injector, $params),
            default => $this->createFileStore($params),
        };
    }

    private function createFileStore(array $params): FileOAuthFlowStore
    {
        $dir = !empty($params['directory']) ? $params['directory'] : (Horde::getTempDir() ?: sys_get_temp_dir());
        $prefix = !empty($params['prefix']) ? $params['prefix'] : 'horde_oauth_';

        return new FileOAuthFlowStore($dir, $prefix);
    }

    private function createSqlStore(Horde_Injector $injector, array $params): SqlOAuthFlowStore
    {
        $dbService = $injector->getInstance(HordeDbService::class);
        $table = !empty($params['table']) ? $params['table'] : 'horde_oauth_flows';

        return new SqlOAuthFlowStore($dbService->getAdapter(), $table);
    }
}
