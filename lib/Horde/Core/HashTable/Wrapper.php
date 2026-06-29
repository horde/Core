<?php

/**
 * Copyright 2013-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package   Core
 */

/**
 * Legacy serializable forwarder over the 'Horde_HashTable' DI key.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2026 Horde LLC
 * @internal
 * @license   http://www.horde.org/licenses/lgpl21 LGPL-2.1
 * @package   Core
 *
 * @deprecated since Horde_Core 3.0.0-RC12.
 *
 * No longer used by Horde_Core_Factory_Cache. The released cache storage
 * (Horde_Cache_Storage_Hashtable v3.0.0) dispatches on
 * `instanceof Horde\HashTable\HashTable`, which this interface-less wrapper
 * does not satisfy. Routing the wrapper through it falls into the legacy
 * array-shaped get() branch and breaks against modern drivers (issue #159).
 *
 * Retained as an orphan symbol so out-of-tree callers that constructed it
 * directly still resolve. Do not add new uses. New code should depend on
 * the PSR-4 {@see Horde\HashTable\HashTable} interface and inject the
 * instance directly.
 */
class Horde_Core_HashTable_Wrapper
{
    /**
     * Redirects calls to the HashTable object.
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(
            [$GLOBALS['injector']->getInstance('Horde_HashTable'), $name],
            $arguments
        );
    }

    /**
     * Redirects get calls to the HashTable object.
     */
    public function __get($name)
    {
        return $GLOBALS['injector']->getInstance('Horde_HashTable')->$name;
    }

    /**
     * Redirects set calls to the HashTable object.
     */
    public function __set($name, $value)
    {
        $GLOBALS['injector']->getInstance('Horde_HashTable')->$name = $value;
    }

}
