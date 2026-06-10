<?php

/**
 * A Horde_Injector:: based Horde_Tree:: factory.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

use Horde\Core\Session\HordeSession;

class Horde_Core_Factory_Tree extends Horde_Core_Factory_Base
{
    /**
     * Singleton instances.
     *
     * @var array
     */
    private $_instances = [];

    /**
     * Return the Horde_Tree:: instance.
     *
     * @param string $name     The name of this tree instance.
     * @param mixed $renderer  The type of tree renderer.
     * @param array $params    Any additional parameters the constructor
     *                         needs. Defined by this class:
     * <pre>
     * 'nosession' - (boolean) Don't store tree state in the session.
     *               DEFAULT: false
     * </pre>
     *
     * @return Horde_Tree_Renderer_Base  The singleton instance.
     * @throws Horde_Tree_Exception
     */
    public function create($name, $renderer, array $params = [])
    {
        $lc_renderer = Horde_String::lower($renderer);
        $id = $name . '|' . $lc_renderer;

        if (!isset($this->_instances[$id])) {
            switch ($lc_renderer) {
                case 'html':
                    $renderer = 'Horde_Core_Tree_Renderer_Html';
                    break;

                case 'javascript':
                    $renderer = 'Horde_Core_Tree_Renderer_Javascript';
                    break;

                case 'simplehtml':
                    $renderer = 'Horde_Core_Tree_Renderer_Simplehtml';
                    break;
            }

            $params['name'] = $name;

            if (empty($params['nosession'])) {
                $params['session'] = [
                    'get' => [__CLASS__, 'getSession'],
                    'set' => [__CLASS__, 'setSession'],
                ];
            }

            $this->_instances[$id] = Horde_Tree_Renderer::factory($renderer, $params);
        }

        return $this->_instances[$id];
    }

    /**
     * Reads a tree expanded-state slot from the modern session.
     *
     * The legacy implementation accepted a Horde_Session mask; raw scoped
     * reads ignore it. The renderer now passes booleans only, no packing
     * required.
     */
    public static function getSession($instance, $id)
    {
        return self::session()->getScoped('horde', 'tree-' . $instance . '/' . $id);
    }

    /**
     */
    public static function setSession($instance, $id, $val)
    {
        $session = self::session();
        $key = 'tree-' . $instance . '/' . $id;
        if ($val) {
            $session->setScoped('horde', $key, $val);
        } else {
            $session->removeScoped('horde', $key);
        }
    }

    /**
     * Resolve the modern session lazily from the global injector.
     */
    private static function session(): HordeSession
    {
        return $GLOBALS['injector']->getInstance(HordeSession::class);
    }
}
