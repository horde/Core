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
use Horde\Core\Service\HordeDbService;
use Horde\Core\Session\HordeSessionFactory;
use Horde\SessionHandler\NativePhpSessionSerializer;
use Horde\SessionHandler\SessionHandler;
use Horde\SessionHandler\Storage\BuiltinBackend;
use Horde\SessionHandler\Storage\FileBackend;
use Horde\SessionHandler\Storage\HashtableBackend;
use Horde\SessionHandler\Storage\SqlBackend;
use Horde\SessionHandler\Storage\StackBackend;
use Horde_HashTable_Base;
use Horde_HashTable_Lock;
use Horde\Injector\Injector;
use Horde_Secret;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;

/**
 * Factory for creating modern SessionHandler from configuration
 *
 * Reads session backend configuration via ConfigLoader (no globals)
 * and constructs a Horde\SessionHandler\SessionHandler with the
 * appropriate storage backend.
 *
 * Supports the same driver types as the legacy Horde_Core_Factory_SessionHandler:
 * builtin, sql, hashtable/memcache, file. Nosql/mongo is dropped.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class SessionHandlerFactory
{
    /**
     * Create SessionHandler instance
     *
     * @param Injector $injector Dependency injector
     * @return SessionHandler Configured session handler
     * @throws RuntimeException If driver is unsupported
     */
    public function create(Injector $injector): SessionHandler
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        $driver = strtolower((string) $state->get('sessionhandler.type', 'builtin'));
        if ($driver === 'none' || $driver === '') {
            $driver = 'builtin';
        }

        $params = $state->get('sessionhandler.params', []);
        if (!is_array($params)) {
            $params = [];
        }

        $backend = match ($driver) {
            'builtin' => $this->createBuiltinBackend($params),
            'sql' => $this->createSqlBackend($injector, $params),
            'hashtable', 'memcache' => $this->createHashtableBackend($injector, $params),
            'file' => $this->createFileBackend($params),
            default => throw new RuntimeException("Unsupported session driver: $driver"),
        };

        if ($this->shouldStack($state, $driver)) {
            $cacheBackend = $this->createHashtableBackend($injector, []);
            $backend = new StackBackend($cacheBackend, $backend);
        }

        return new SessionHandler(
            backend: $backend,
            serializer: new NativePhpSessionSerializer(),
            sessionFactory: $this->createSessionFactory($injector),
            events: $this->getEventDispatcher($injector),
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createBuiltinBackend(array $params): BuiltinBackend
    {
        return new BuiltinBackend(path: $params['path'] ?? session_save_path());
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createSqlBackend(Injector $injector, array $params): SqlBackend
    {
        $dbService = $injector->getInstance(HordeDbService::class);
        $db = $dbService->getAdapter();
        $table = $params['table'] ?? 'horde_sessionhandler';

        return new SqlBackend(
            db: $db,
            table: $table,
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createHashtableBackend(Injector $injector, array $params): HashtableBackend
    {
        $ht = $injector->getInstance('Horde_HashTable');

        if (!$ht instanceof Horde_HashTable_Base || !$ht instanceof Horde_HashTable_Lock) {
            throw new RuntimeException(
                'HashTable backend requires a Horde_HashTable that implements both Horde_HashTable_Base and Horde_HashTable_Lock'
            );
        }

        return new HashtableBackend(
            hashTable: $ht,
            track: (bool) ($params['track'] ?? false),
            trackKey: $params['track_id'] ?? 'horde_sessions_track_ht',
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createFileBackend(array $params): FileBackend
    {
        $path = $params['path'] ?? session_save_path();
        if ($path === '' || $path === false) {
            $path = sys_get_temp_dir();
        }

        return new FileBackend(path: $path);
    }

    /**
     * Determine whether to wrap the primary backend in a HashTable cache stack.
     *
     * Stacking is enabled when the config has sessionhandler.hashtable or
     * sessionhandler.memcache set AND the primary driver is not already
     * hashtable-based or builtin.
     */
    private function shouldStack(State $state, string $driver): bool
    {
        if (in_array($driver, ['builtin', 'hashtable', 'memcache'], true)) {
            return false;
        }

        return (bool) $state->get('sessionhandler.hashtable', false)
            || (bool) $state->get('sessionhandler.memcache', false);
    }

    private function createSessionFactory(Injector $injector): HordeSessionFactory
    {
        $encryptor = null;
        $decryptor = null;

        try {
            $secret = $injector->getInstance('Horde_Secret');
            if ($secret instanceof Horde_Secret) {
                $encryptor = static fn(string $plaintext): string => $secret->write($secret->getKey(), $plaintext);
                $decryptor = static fn(string $ciphertext): string => $secret->read($secret->getKey(), $ciphertext);
            }
        } catch (Throwable) {
            // No encryption available
        }

        return new HordeSessionFactory(
            encryptor: $encryptor,
            decryptor: $decryptor,
        );
    }

    private function getEventDispatcher(Injector $injector): ?EventDispatcherInterface
    {
        try {
            $dispatcher = $injector->getInstance(EventDispatcherInterface::class);
            return $dispatcher instanceof EventDispatcherInterface ? $dispatcher : null;
        } catch (Throwable) {
            return null;
        }
    }
}
