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

use Horde\Core\Service\NullOAuthProviderConfigRepository;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Db\Adapter;
use Horde\Horde\Service\SqlOAuthProviderConfigRepository;
use Horde\Secret\SecretManager;
use Horde_Injector;
use Throwable;

/**
 * Factory for OAuthProviderConfigRepository.
 *
 * Tries to build SqlOAuthProviderConfigRepository when a DB adapter is
 * available. Falls back gracefully to NullOAuthProviderConfigRepository
 * when the DB or the SQL implementation class is unavailable.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class OAuthProviderConfigRepositoryFactory
{
    public function create(Horde_Injector $injector): OAuthProviderConfigRepository
    {
        if (class_exists(SqlOAuthProviderConfigRepository::class)) {
            try {
                $db = $injector->getInstance(Adapter::class);
            } catch (Throwable) {
                return new NullOAuthProviderConfigRepository();
            }

            try {
                $secret = $injector->getInstance(SecretManager::class);
            } catch (Throwable) {
                $secret = null;
            }

            return new SqlOAuthProviderConfigRepository($db, $secret);
        }

        return new NullOAuthProviderConfigRepository();
    }
}
