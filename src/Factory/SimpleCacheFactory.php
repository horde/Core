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

use Horde\Cache\ApcuStorage;
use Horde\Cache\Cache;
use Horde\Cache\FileStorage;
use Horde\Cache\HashtableStorage;
use Horde\Cache\NullStorage;
use Horde\Cache\SqlStorage;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\Injector\Injector;
use Horde_Db_Adapter;
use Horde_HashTable_Base;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Factory for PSR-16 SimpleCache (Horde\Cache\Cache)
 *
 * Modern mirror of Horde_Core_Factory_Cache. Uses ConfigLoader
 * instead of global $conf and returns a Horde\Cache\Cache instance
 * implementing Psr\SimpleCache\CacheInterface.
 *
 * Supported drivers: file, sql, hashtable, memcache, apcu, null/none.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class SimpleCacheFactory
{
    /**
     * Create a PSR-16 Cache instance
     */
    public function create(Injector $injector): Cache
    {
        $state = $this->loadConfig($injector);
        $driver = $this->resolveDriver($state);
        $logger = $this->getLogger($injector);
        $storage = $this->createStorage($driver, $state, $injector, $logger);

        $params = ['compress' => true];
        $lifetime = $state->get('cache.default_lifetime');
        if ($lifetime !== null) {
            $params['lifetime'] = (int) $lifetime;
        }

        return new Cache($storage, $params);
    }

    private function loadConfig(Injector $injector): State
    {
        $loader = $injector->getInstance(ConfigLoader::class);

        return $loader->load('horde');
    }

    private function resolveDriver(State $state): string
    {
        $driver = strtolower((string) $state->get('cache.driver', 'null'));

        return match ($driver) {
            'none', '' => 'null',
            'xcache' => php_sapi_name() === 'cli' ? 'null' : 'apcu',
            default => $driver,
        };
    }

    private function getLogger(Injector $injector): LoggerInterface
    {
        try {
            return $injector->getInstance(LoggerInterface::class);
        } catch (Throwable) {
            return new NullLogger();
        }
    }

    private function createStorage(
        string $driver,
        State $state,
        Injector $injector,
        LoggerInterface $logger,
    ): \Horde\Cache\SimpleCacheStorage {
        return match ($driver) {
            'file' => $this->createFileStorage($state, $logger),
            'sql' => $this->createSqlStorage($injector, $logger),
            'hashtable', 'memcache' => $this->createHashtableStorage($injector, $logger),
            'apcu' => new ApcuStorage($logger),
            default => new NullStorage($logger),
        };
    }

    private function createFileStorage(State $state, LoggerInterface $logger): FileStorage
    {
        $params = $state->get('cache.params', []);
        if (!is_array($params)) {
            $params = [];
        }
        $dir = $params['dir'] ?? sys_get_temp_dir();
        $prefix = $params['prefix'] ?? 'cache_';
        $sub = (int) ($params['sub'] ?? 0);

        return new FileStorage(
            logger: $logger,
            dir: $dir,
            prefix: $prefix,
            sub: $sub,
        );
    }

    private function createSqlStorage(Injector $injector, LoggerInterface $logger): SqlStorage
    {
        $db = $injector->getInstance(Horde_Db_Adapter::class);

        return new SqlStorage(db: $db, logger: $logger);
    }

    private function createHashtableStorage(Injector $injector, LoggerInterface $logger): HashtableStorage
    {
        $hashtable = $injector->getInstance(Horde_HashTable_Base::class);

        return new HashtableStorage(hashtable: $hashtable, logger: $logger);
    }
}
