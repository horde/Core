<?php
/**
 * Copyright 1999-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Ralf Lang <lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
declare(strict_types=1);
namespace Horde\Core\Config;

/**
 * Horde Config encapsulated in an object
 *
 * This is basically an injectable $GLOBALS['conf']
 *
 * @author   Ralf Lang <lang@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL
 * @package  Core
 */
class State
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
    public function __construct(array $conf = null)
    {
        $this->conf = $conf ?? $GLOBALS['conf'] ?? null;
        // If we still have no array, give up.
        if (empty($this->conf)) {
            throw new \Horde_Exception(
                'Config neither passed nor available from global'
            );
        }
    }
    /**
     * TODO: While it's probably wise NOT to implement ArrayAccess
     * and keep objects of this class more or less static/readonly,
     * We should have some OO way of accessing single config keys
     */

    /**
     * Return the config array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->conf;
    }
}
