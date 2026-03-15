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

use Horde\Core\Auth\AuthService;
use Horde\Core\Service\HordeDbService;
use Horde\Core\Config\ConfigLoader;
use Horde_Auth_Sql;
use Horde_Core_Auth_Application;
use Horde_Injector;
use Horde_Log_Logger;
use RuntimeException;

/**
 * Factory for creating AuthService with proper backend
 *
 * Creates auth service from configuration without globals.
 * Mirrors legacy Horde_Core_Factory_Auth behavior:
 * - Returns Horde_Core_Auth_Application wrapping base driver
 * - For 'horde' app: creates base driver (Horde_Auth_Sql, etc)
 * - Wraps in Application auth for hooks and app-specific features
 *
 * Currently implemented:
 * - 'sql': SQL authentication (Horde_Auth_Sql)
 * - 'auto': Defaults to SQL
 *
 * TODO: Implement additional drivers as needed:
 * - 'ldap': LDAP authentication (Horde_Core_Auth_Ldap)
 * - 'msad': Microsoft Active Directory (Horde_Core_Auth_Msad)
 * - 'composite': Multi-backend auth (Horde_Core_Auth_Composite)
 * - 'application': App-specific auth (other apps, not horde)
 * - 'shibboleth', 'x509', 'imsp', etc.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class AuthServiceFactory
{
    /**
     * Create AuthService instance
     *
     * Returns Horde_Core_Auth_Application wrapping the configured
     * base auth driver, matching legacy factory behavior.
     *
     * @param Horde_Injector $injector Dependency injector
     * @return AuthService Auth service with configured backend
     * @throws RuntimeException If driver unsupported
     */
    public function create(Horde_Injector $injector): AuthService
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        $driver = $state->get('auth.driver', 'auto');
        $params = $state->get('auth.params', []);

        // Get dependencies
        $dbService = $injector->getInstance(HordeDbService::class);
        $logger = $injector->getInstance(Horde_Log_Logger::class);

        // Create base driver based on config
        $baseDriver = match ($driver) {
            'sql' => $this->createSqlBackend($params, $dbService, $logger),
            'auto' => $this->createSqlBackend($params, $dbService, $logger),
            default => throw new RuntimeException("Unsupported auth driver: $driver (TODO: implement)"),
        };

        // Wrap in Horde_Core_Auth_Application (matches legacy factory)
        $authApp = new Horde_Core_Auth_Application([
            'app' => 'horde',
            'base' => $baseDriver,
            'logger' => $logger,
        ]);

        return new AuthService($authApp);
    }

    /**
     * Create SQL authentication backend
     *
     * Matches legacy factory behavior (Core/Factory/Auth.php lines 169-173):
     * - Gets DB adapter from Horde_Core_Factory_Db
     * - Creates Horde_Auth_Sql with adapter and params
     * - Adds logger and default_user from registry
     *
     * @param array $params Auth configuration parameters
     * @param HordeDbService $dbService Database service
     * @param Horde_Log_Logger $logger Logger instance
     * @return Horde_Auth_Sql SQL auth backend instance
     */
    private function createSqlBackend(
        array $params,
        HordeDbService $dbService,
        Horde_Log_Logger $logger
    ): Horde_Auth_Sql {
        $authParams = [
            'db' => $dbService->getAdapter(),
            'table' => $params['table'] ?? 'horde_users',
            'username_field' => $params['username_field'] ?? 'user_uid',
            'password_field' => $params['password_field'] ?? 'user_pass',
            'encryption' => $params['encryption'] ?? 'ssha',
            'show_encryption' => $params['show_encryption'] ?? false,
            'logger' => $logger,
            // Note: default_user and count_bad_logins/login_block handled by Application wrapper
        ];

        return new Horde_Auth_Sql($authParams);
    }
}
