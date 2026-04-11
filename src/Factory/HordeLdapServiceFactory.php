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

use Horde\Core\Service\StandardHordeLdapService;
use Horde\Core\Config\ConfigLoader;
use Horde_Cache;
use Horde_Injector;
use Horde_Ldap;
use Exception;
use RuntimeException;

/**
 * Factory for creating HordeLdapService from configuration
 *
 * Creates LDAP connection instances based on configuration
 * without relying on global state or legacy factories.
 *
 * Supports connection pooling - services with identical configuration
 * share the same connection instance.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class HordeLdapServiceFactory
{
    /**
     * Connection pool indexed by config signature
     *
     * @var array<string, Horde_Ldap>
     */
    private array $adapters = [];

    /**
     * Create LDAP service from configuration
     *
     * @param Horde_Injector $injector Dependency injector
     * @param string $serviceId Service identifier ('horde', 'horde:groups', etc.)
     * @return StandardHordeLdapService LDAP service instance
     * @throws RuntimeException If no LDAP configuration found
     */
    public function create(Horde_Injector $injector, string $serviceId = 'horde'): StandardHordeLdapService
    {
        $loader = $injector->getInstance(ConfigLoader::class);

        // Parse service ID: 'horde' or 'horde:groups'
        [$app, $service] = $this->parseServiceId($serviceId);

        $state = $loader->load($app);

        // Resolve config for this service
        $ldapConfig = $this->resolveConfig($state, $service);

        // Generate signature for connection pooling
        $signature = $this->generateSignature($ldapConfig);

        // Return existing adapter or create new
        if (!isset($this->adapters[$signature])) {
            $this->adapters[$signature] = $this->createAdapter($ldapConfig, $injector);
        }

        return new StandardHordeLdapService($this->adapters[$signature]);
    }

    /**
     * Parse service identifier into app and service components
     *
     * Examples:
     * - 'horde' → ['horde', null]
     * - 'horde:groups' → ['horde', 'groups']
     * - 'imp:storage' → ['imp', 'storage']
     *
     * @param string $serviceId Service identifier
     * @return array{0: string, 1: string|null} [app, service]
     */
    private function parseServiceId(string $serviceId): array
    {
        if (str_contains($serviceId, ':')) {
            return explode(':', $serviceId, 2);
        }
        return [$serviceId, null];
    }

    /**
     * Create LDAP adapter from configuration
     *
     * Extracted for testability - can be overridden in tests.
     *
     * @param array $ldapConfig LDAP configuration
     * @param Horde_Injector $injector Dependency injector
     * @return Horde_Ldap LDAP adapter
     */
    protected function createAdapter(array $ldapConfig, Horde_Injector $injector): Horde_Ldap
    {
        // Add optional cache if available
        try {
            $cache = $injector->getInstance('Horde_Cache');
            if ($cache instanceof Horde_Cache) {
                $ldapConfig['cache'] = $cache;
                $ldapConfig['cache_root_dse'] = true;
            }
        } catch (Exception $e) {
            // Cache not available, continue without it
        }

        return new Horde_Ldap($ldapConfig);
    }

    /**
     * Resolve LDAP configuration for service
     *
     * Configuration priority:
     * 1. Service-specific: $conf['ldap']['service'][$service] (if service given)
     * 2. Default app: $conf['ldap']
     *
     * @param \Horde\Core\Config\State $state Configuration state
     * @param string|null $service Optional service name
     * @return array LDAP configuration
     * @throws RuntimeException If no LDAP configuration found
     */
    private function resolveConfig($state, ?string $service): array
    {
        // Service-specific config: ldap.service.groups
        if ($service !== null) {
            $serviceKey = "ldap.service.$service";
            if ($state->has($serviceKey)) {
                return $state->get($serviceKey);
            }
        }

        // Default app config: ldap
        if ($state->has('ldap')) {
            $config = $state->get('ldap');

            // BC: If config is nested under default key, extract it
            if (isset($config['hostspec']) || isset($config['basedn'])) {
                return $config;
            }

            // Otherwise might be service-keyed structure, try default
            if (isset($config['default'])) {
                return $config['default'];
            }

            return $config;
        }

        throw new RuntimeException(
            'No LDAP configuration found for service: '
            . ($service ? "$service" : 'default')
        );
    }

    /**
     * Generate config signature for connection pooling
     *
     * @param array $config LDAP configuration
     * @return string Configuration signature
     */
    private function generateSignature(array $config): string
    {
        // Remove cache references (not part of connection identity)
        unset($config['cache'], $config['cache_root_dse']);

        // Sort for consistent hashing
        ksort($config);

        return hash('md5', serialize($config));
    }
}
