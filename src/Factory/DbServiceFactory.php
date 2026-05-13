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

use Horde\Core\Service\StandardHordeDbService;
use Horde\Core\Config\ConfigLoader;
use Horde\Db\Adapter;
use Horde\Db\Adapter\Mysqli;
use Horde\Db\Adapter\Pdo\Mysql as PdoMysql;
use Horde\Db\Adapter\Pdo\Pgsql as PdoPgsql;
use Horde\Db\Adapter\Pdo\Sqlite as PdoSqlite;
use Horde\Injector\Injector;
use InvalidArgumentException;

/**
 * Factory for creating HordeDbService from configuration
 *
 * Creates database adapter instances based on configuration
 * without relying on global state or legacy factories.
 *
 * Supports connection pooling - services with identical configuration
 * share the same adapter instance.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class DbServiceFactory
{
    /**
     * Connection pool indexed by config signature
     *
     * @var array<string, Adapter>
     */
    private array $adapters = [];

    /**
     * Create database service from configuration
     *
     * @param Injector $injector Dependency injector
     * @param string $serviceId Service identifier ('horde', 'horde:perms', etc.)
     * @return StandardHordeDbService Database service instance
     * @throws InvalidArgumentException If phptype unsupported
     */
    public function create(Injector $injector, string $serviceId = 'horde'): StandardHordeDbService
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        // Resolve config for this service
        $sqlConfig = $this->resolveConfig($state, $serviceId);

        // Check if explicitly using horde connection
        if (($sqlConfig['driverconfig'] ?? null) === 'horde' && $serviceId !== 'horde') {
            // Recursive: use horde's connection
            return $this->create($injector, 'horde');
        }

        // Generate signature for connection pooling
        $signature = $this->generateSignature($sqlConfig);

        // Return existing adapter or create new
        if (!isset($this->adapters[$signature])) {
            $this->adapters[$signature] = $this->createAdapter($sqlConfig);
        }

        return new StandardHordeDbService($this->adapters[$signature]);
    }

    /**
     * Create database adapter from configuration
     *
     * Extracted for testability - can be overridden in tests.
     *
     * @param array $sqlConfig SQL configuration
     * @return Horde_Db_Adapter Database adapter
     */
    protected function createAdapter(array $sqlConfig): Adapter
    {
        $phptype = $sqlConfig['phptype'] ?? 'mysqli';
        $adapterClass = $this->getAdapterClass($phptype);
        $connectionConfig = $this->buildConnectionConfig($sqlConfig);
        return new $adapterClass($connectionConfig);
    }

    /**
     * Resolve database configuration for service
     *
     * @param \Horde\Core\Config\State $state Configuration state
     * @param string $serviceId Service identifier
     * @return array Database configuration
     */
    private function resolveConfig($state, string $serviceId): array
    {
        if ($serviceId === 'horde') {
            // System-wide horde connection
            return $state->get('sql', []);
        }

        // Parse service ID: 'horde:perms' => service = 'perms'
        $parts = explode(':', $serviceId, 2);
        $service = $parts[1] ?? null;

        if (!$service) {
            // Invalid format, fall back to system SQL
            return $state->get('sql', []);
        }

        // Get service-specific params
        $serviceParams = $state->get("{$service}.params", []);

        // Check if explicitly using horde connection
        if (($serviceParams['driverconfig'] ?? null) === 'horde') {
            return ['driverconfig' => 'horde'];
        }

        // Merge with system defaults if service has any DB params
        if ($this->hasDbParams($serviceParams)) {
            $sqlDefaults = $state->get('sql', []);
            return array_merge($sqlDefaults, $serviceParams);
        }

        // No service-specific config, use system SQL
        return $state->get('sql', []);
    }

    /**
     * Check if params contain database-specific configuration
     *
     * @param array $params Configuration parameters
     * @return bool True if DB params present
     */
    private function hasDbParams(array $params): bool
    {
        $dbKeys = ['phptype', 'username', 'password', 'database', 'dbname', 'host', 'hostspec', 'port'];
        foreach ($dbKeys as $key) {
            if (isset($params[$key])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate config signature for connection pooling
     *
     * @param array $config Database configuration
     * @return string Configuration signature
     */
    private function generateSignature(array $config): string
    {
        // Remove non-connection fields
        unset($config['driverconfig']);

        // Sort for consistent hashing
        ksort($config);

        return hash('md5', serialize($config));
    }

    /**
     * Get adapter class name from phptype
     *
     * @param string $phptype Database type ('mysqli', 'mysql', 'pgsql', etc.)
     * @return string Fully qualified adapter class name
     * @throws InvalidArgumentException If phptype unsupported
     */
    private function getAdapterClass(string $phptype): string
    {
        return match ($phptype) {
            'mysqli' => Mysqli::class,
            'mysql' => PdoMysql::class,
            'pgsql' => PdoPgsql::class,
            'sqlite' => PdoSqlite::class,
            default => throw new InvalidArgumentException("Unsupported phptype: $phptype"),
        };
    }

    /**
     * Build adapter connection configuration
     *
     * Normalizes config keys across different naming conventions.
     *
     * @param array $sqlConfig Raw SQL configuration
     * @return array Normalized adapter configuration
     */
    private function buildConnectionConfig(array $sqlConfig): array
    {
        return [
            'username' => $sqlConfig['username'] ?? '',
            'password' => $sqlConfig['password'] ?? '',
            'database' => $sqlConfig['database'] ?? $sqlConfig['dbname'] ?? '',
            'host' => $sqlConfig['hostspec'] ?? $sqlConfig['host'] ?? 'localhost',
            'port' => $sqlConfig['port'] ?? 3306,
            'charset' => $sqlConfig['charset'] ?? 'UTF-8',
        ];
    }
}
