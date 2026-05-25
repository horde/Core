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
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\HashTable\ConnectionException;
use Horde\HashTable\Driver\Memcache;
use Horde\HashTable\Driver\Memory;
use Horde\HashTable\Driver\Redis;
use Horde\HashTable\HashTable;
use Horde\HashTable\LockableHashTable;
use Horde\HashTable\Redis\Config\RedisClientFactory;
use Horde\HashTable\Redis\Config\RedisConfigMapper;
use Horde\HashTable\RedisHashTable;
use Horde\Injector\Injector;
use Horde\Memcache\MemcacheApi;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/**
 * Factory for modern Horde\HashTable driver instances.
 *
 * Reads configuration via ConfigLoader (dot-notation State) and produces the
 * appropriate driver. Prefers ext-redis when available; falls back to
 * Predis ^3 for Redis. For Memcache, delegates to MemcacheApi.
 *
 * Three entry points match the interface hierarchy:
 * - create()         → HashTable (any driver)
 * - createLockable() → LockableHashTable (Redis or Memcache)
 * - createRedis()    → RedisHashTable (Redis only)
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class HashTableFactory
{
    /**
     * Create a HashTable instance based on configuration.
     *
     * @param Injector $injector  The DI container.
     *
     * @return HashTable
     */
    public function create(Injector $injector): HashTable
    {
        $state = $this->loadConfig($injector);
        $driver = $this->resolveDriver($state);
        $logger = $this->getLogger($injector);
        $prefix = (string) $state->get('hashtable.prefix', 'hht_');

        return match ($driver) {
            'predis', 'redis' => $this->createRedisDriver($state, $logger, $prefix),
            'memcache' => $this->createMemcacheDriver($injector, $logger, $prefix),
            default => new Memory(prefix: $prefix, logger: $logger),
        };
    }

    /**
     * Create a LockableHashTable instance.
     *
     * Only Redis and Memcache drivers provide real cross-process locking.
     *
     * @param Injector $injector  The DI container.
     *
     * @return LockableHashTable
     *
     * @throws RuntimeException If the configured driver does not support locking.
     */
    public function createLockable(Injector $injector): LockableHashTable
    {
        $instance = $this->create($injector);

        if (!$instance instanceof LockableHashTable) {
            throw new RuntimeException(
                'Configured hashtable driver (' . $instance::class . ') does not support locking. '
                . 'Configure Redis or Memcache for LockableHashTable.'
            );
        }

        return $instance;
    }

    /**
     * Create a RedisHashTable instance.
     *
     * Only the Redis driver provides native data structure operations.
     *
     * @param Injector $injector  The DI container.
     *
     * @return RedisHashTable
     *
     * @throws RuntimeException If the configured driver is not Redis.
     */
    public function createRedis(Injector $injector): RedisHashTable
    {
        $instance = $this->create($injector);

        if (!$instance instanceof RedisHashTable) {
            throw new RuntimeException(
                'Configured hashtable driver (' . $instance::class . ') is not Redis. '
                . 'Configure Redis (predis or ext-redis) for RedisHashTable.'
            );
        }

        return $instance;
    }

    private function loadConfig(Injector $injector): State
    {
        $loader = $injector->get(ConfigLoader::class);

        return $loader->load('horde');
    }

    private function resolveDriver(State $state): string
    {
        if ($state->get('memcache.enabled')) {
            return 'memcache';
        }

        return strtolower((string) $state->get('hashtable.driver', 'memory'));
    }

    private function getLogger(Injector $injector): LoggerInterface
    {
        try {
            return $injector->get(LoggerInterface::class);
        } catch (Throwable) {
            return new NullLogger();
        }
    }

    /**
     * Build a Redis driver using typed config objects.
     */
    private function createRedisDriver(State $state, LoggerInterface $logger, string $prefix): Redis
    {
        $params = $state->get('hashtable.params', []);
        if (!is_array($params)) {
            $params = [];
        }

        $config = RedisConfigMapper::fromArray($params, $prefix);
        $factory = new RedisClientFactory();
        $client = $factory->create($config);

        $lockTimeout = (int) $state->get('hashtable.lock_timeout', 30);

        return new Redis(
            client: $client,
            prefix: $prefix,
            logger: $logger,
            lockTimeout: $lockTimeout,
        );
    }

    /**
     * Build a Memcache driver from the injector.
     */
    private function createMemcacheDriver(Injector $injector, LoggerInterface $logger, string $prefix): Memcache
    {
        $memcache = $injector->get(MemcacheApi::class);

        return new Memcache(
            memcache: $memcache,
            prefix: $prefix,
            logger: $logger,
        );
    }
}
