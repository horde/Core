<?php

use Horde\HashTable\HashTable;
use Horde\Injector\Injector;

/**
 * A Horde_Injector:: based Horde_Cache:: factory.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * A Horde_Injector:: based Horde_Cache:: factory.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_Core_Factory_Cache extends Horde_Core_Factory_Injector
{
    /**
     * Contains the storage driver used by the Cache object.
     *
     * @since 2.5.0
     *
     * @var Horde_Cache_Storage
     */
    public $storage;

    /**
     * Return the global Horde_Cache instance.
     *
     * @return Horde_Cache  Cache object.
     * @throws Horde_Cache_Exception
     */
    public function create(Horde_Injector|Injector $injector)
    {
        global $conf;

        $params = [
            'compress' => true,
            'logger' => $injector->getInstance('Horde_Core_Log_Wrapper'),
        ];
        if (isset($conf['cache']['default_lifetime'])) {
            $params['lifetime'] = $conf['cache']['default_lifetime'];
        }

        $driver = $this->getDriverName();
        $sparams = Horde::getDriverConfig('cache', $driver);

        switch ($driver) {
            case 'hashtable':
                // DEPRECATED
            case 'memcache':
                /* Resolve a modern Horde\HashTable\HashTable so
                 * Horde_Cache_Storage_Hashtable v3.0.0 routes to
                 * Horde\Cache\HashtableStorage. A failure to resolve the
                 * modern binding surfaces as Horde_Cache_Exception with a
                 * descriptive message; the deprecated
                 * Horde_Core_HashTable_Wrapper is no longer consulted (see
                 * {@see _resolveHashTable()} and issue #159). */
                $sparams['hashtable'] = $this->_resolveHashTable($injector);
                $driver = 'Horde_Cache_Storage_Hashtable';
                unset($sparams['driverconfig'], $sparams['umask']);
                break;

            case 'nosql':
                $nosql = $injector->getInstance('Horde_Core_Factory_Nosql')->create('horde', 'cache');
                if ($nosql instanceof Horde_Mongo_Client) {
                    $sparams['mongo_db'] = $nosql;
                    $driver = 'Horde_Cache_Storage_Mongo';
                } else {
                    $driver = 'Horde_Cache_Storage_Null';
                }
                unset($sparams['driverconfig'], $sparams['umask']);
                break;

            case 'sql':
                $sparams['db'] = $injector->getInstance('Horde_Core_Factory_Db')->create('horde', 'cache');
                unset($sparams['driverconfig'], $sparams['umask']);
                break;
        }

        $storage = $this->storage = $this->_getStorage($driver, $sparams);

        if (!empty($conf['cache']['use_memorycache'])
            && in_array($driver, ['file', 'sql'])) {
            switch (Horde_String::lower($conf['cache']['use_memorycache'])) {
                case 'hashtable':
                case 'memcache':
                    $storage = new Horde_Cache_Storage_Stack([
                        'stack' => [
                            $this->_getStorage(
                                $conf['cache']['use_memorycache'],
                                [
                                    'hashtable' => $this->_resolveHashTable($injector),
                                ]
                            ),
                            $storage,
                        ],
                    ]);
                    break;
            }
        }

        return new Horde_Cache($storage, $params);
    }

    /**
     * Return the driver name.
     *
     * @since 2.5.0
     *
     * @return string  Lowercase driver name.
     */
    public function getDriverName()
    {
        global $conf;

        $driver = empty($conf['cache']['driver'])
            ? 'null'
            : Horde_String::lower($conf['cache']['driver']);

        switch ($driver) {
            case 'none':
                $driver = 'null';
                break;

            case 'xcache':
                if (Horde_Cli::runningFromCLI()) {
                    $driver = 'null';
                }
                break;
        }

        return $driver;
    }

    /**
     * Create the Cache storage backend.
     *
     * @param string $driver  The storage driver name.
     * @param array  $params  The storage backend parameters.
     *
     * @return Horde_Cache_Storage_Base  A cache storage backend.
     */
    protected function _getStorage($driver, $params)
    {
        try {
            $class = $this->_getDriverName($driver, 'Horde_Cache_Storage');
        } catch (Horde_Exception $e) {
            $class = 'Horde_Cache_Storage_Null';
        }

        return new $class($params);
    }

    /**
     * Resolve a modern Horde\HashTable\HashTable for cache storage.
     *
     * The released Horde_Cache_Storage_Hashtable v3.0.0 dispatches on
     * `instanceof Horde\HashTable\HashTable`. The legacy
     * Horde_Core_HashTable_Wrapper does not implement that interface, so
     * routing it through Storage_Hashtable lands in the legacy array-shaped
     * get() branch and fails against modern drivers (issue #159). The
     * wrapper is therefore not consulted as a fallback here. If the modern
     * binding cannot be resolved the failure is surfaced loudly so
     * operators see the misconfiguration instead of a silent backend swap.
     *
     * @param Horde_Injector|Injector $injector
     *
     * @return HashTable
     *
     * @throws Horde_Cache_Exception When the modern Horde\HashTable\HashTable
     *                               binding cannot be resolved.
     */
    protected function _resolveHashTable($injector): HashTable
    {
        try {
            return $injector->getInstance(HashTable::class);
        } catch (Throwable $e) {
            $hashtableDriver = $this->_describeConfiguredHashTableDriver();
            /* Horde_Cache_Exception extends Horde_Exception_Wrapped, whose
             * constructor accepts only (message, code) and drops a 3rd
             * Throwable arg. The original error is preserved inline in the
             * message so the diagnostic survives the wrapping. */
            throw new Horde_Cache_Exception(
                sprintf(
                    'Cache is configured to use a hashtable backend '
                    . '(cache.driver=%s%s), but the modern '
                    . 'Horde\\HashTable\\HashTable binding could not be '
                    . 'resolved: %s: %s. Check $conf[\'hashtable\'] and the '
                    . 'memcache/redis client configuration. The deprecated '
                    . 'Horde_Core_HashTable_Wrapper is no longer used as a '
                    . 'fallback.',
                    $this->getDriverName(),
                    $hashtableDriver !== null
                        ? ', hashtable.driver=' . $hashtableDriver
                        : '',
                    $e::class,
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Describe the configured hashtable driver for diagnostic messages.
     *
     * Read-only inspection of $conf['hashtable']['driver']. Returns null if
     * unset, so failure messages can omit the segment cleanly rather than
     * print "hashtable.driver=".
     *
     * @return string|null
     */
    private function _describeConfiguredHashTableDriver(): ?string
    {
        global $conf;

        return empty($conf['hashtable']['driver'])
            ? null
            : (string) $conf['hashtable']['driver'];
    }

}
