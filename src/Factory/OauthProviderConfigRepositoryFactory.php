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

use Horde\Core\Service\NullOauthProviderConfigRepository;
use Horde\Core\Service\OauthProviderConfigRepository;
use Horde\Db\Adapter;
use Horde\Horde\Service\SqlOauthProviderConfigRepository;
use Horde\Secret\SecretManager;
use Horde_Injector;
use Throwable;

/**
 * Factory for OauthProviderConfigRepository.
 *
 * Tries to build SqlOauthProviderConfigRepository when a DB adapter is
 * available. Falls back gracefully to NullOauthProviderConfigRepository
 * when the DB or the SQL implementation class is unavailable.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class OauthProviderConfigRepositoryFactory
{
    public function create(Horde_Injector $injector): OauthProviderConfigRepository
    {
        if (class_exists(SqlOauthProviderConfigRepository::class)) {
            try {
                $db = $injector->getInstance(Adapter::class);
                $secret = $injector->getInstance(SecretManager::class);
                return new SqlOauthProviderConfigRepository($db, $secret);
            } catch (Throwable) {
                return new NullOauthProviderConfigRepository();
            }
        }

        return new NullOauthProviderConfigRepository();
    }
}
