<?php

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
declare(strict_types=1);

namespace Horde\Core\Config;

use ArrayAccess;
use RuntimeException;

/**
 * Horde Config encapsulated in an object
 *
 * This is basically an injectable $GLOBALS['conf']
 * Provides read-only access with support for dot notation.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
class State implements ArrayAccess
{
    protected $conf = [];

    /**
     * Constructor
     *
     * The preferred way is to actually pass the config array
     * However we fall back to $GLOBALS['conf'] for the time being
     *
     * @param array $conf The config tree as provided by registry
     */
    public function __construct(?array $conf = null)
    {
        // If explicitly passed (even if empty array), use it
        // Otherwise fall back to $GLOBALS['conf']
        if ($conf !== null) {
            $this->conf = $conf;
        } elseif (isset($GLOBALS['conf'])) {
            $this->conf = $GLOBALS['conf'];
        } else {
            throw new \Horde_Exception(
                'Config neither passed nor available from global'
            );
        }
    }

    /**
     * Get config value with support for dot notation
     *
     * @param string $key Config key (supports dot notation: 'admin_api.enabled')
     * @param mixed $default Default value if not found
     * @return mixed Config value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Support dot notation: 'admin_api.enabled'
        $keys = explode('.', $key);
        $value = $this->conf;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Check if key exists (supports dot notation)
     *
     * @param string $key Config key
     * @return bool True if key exists
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->conf;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * Return the config array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->conf;
    }

    // ArrayAccess implementation for backward compatibility
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->conf);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset): mixed
    {
        return $this->conf[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        throw new RuntimeException('ConfigState is immutable');
    }

    public function offsetUnset($offset): void
    {
        throw new RuntimeException('ConfigState is immutable');
    }
}
