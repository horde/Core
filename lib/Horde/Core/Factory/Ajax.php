<?php
/**
 * A Horde_Injector:: based Horde_Core_Ajax_Application:: factory.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * A Horde_Injector:: based Horde_Core_Ajax_Application:: factory.
 *
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Michael Slusarz <slusarz@horde.org>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_Core_Factory_Ajax extends Horde_Core_Factory_Base
{
    /**
     * Return a Horde_Core_Ajax_Application instance.
     *
     * @param string $app            The application name.
     * @param Horde_Variables $vars  Form/request data.
     * @param string $action         The AJAX action to perform.
     * @param string $token          Session token.
     *
     * @return Horde_Core_Ajax_Application  The requested instance.
     * @throws Horde_Exception
     */
    public function create($app, $vars, $action = null, $token = null)
    {
        $class = 'Horde\\' . ucfirst($app) . '\\Ajax\\Application';

        if (class_exists($class)) {
            return new $class($app, $vars, $action, $token);
        }
        $class = $app . '_Ajax_Application';

        if (class_exists($class)) {
            return new $class($app, $vars, $action, $token);
        }

        throw new LogicException('Ajax configuration for ' . $app . ' not found.');
    }

}
