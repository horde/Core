<?php

/**
 * Copyright 2003-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Jason M. Felice <jason.m.felice@gmail.com>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

/**
 * The Horde_Core_Ui_VarRenderer:: class provides base functionality for other
 * Horde UI elements.
 *
 * @author   Jason M. Felice <jason.m.felice@gmail.com>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */
class Horde_Core_Ui_VarRenderer
{
    /**
     * Parameters which change this renderer's behavior.
     *
     * @var array
     */
    protected $_params;

    /**
     * Constructs a new renderer.
     *
     * @param array $params  The name of the variable which will track this UI
     *                       widget's state.
     */
    public function __construct($params = [])
    {
        $this->_params = $params;
    }

    /**
     * Constructs a new instance.
     *
     * @param mixed $driver  This is the renderer subclass we will instantiate.
     *                       If an array is passed, the first element is the
     *                       library path and the second element is the driver
     *                       name.
     * @param array $params  Parameters specific to the subclass.
     *
     * @return Horde_Core_Ui_VarRenderer  A subclass instance.
     * @throws Horde_Exception
     */
    public static function factory($driver, $params = [])
    {
        if (is_array($driver)) {
            $app = $driver[0];
            $driver = $driver[1];
        } else {
            $app = '';
        }

        $driver = Horde_String::ucfirst(basename($driver));
        $class = (empty($app) ? 'Horde_Core' : $app) . '_Ui_VarRenderer_' . $driver;

        $ok = class_exists($class);

        // TODO: Eliminate after renaming Horde_Ui_VarRenderer_* classes in other apps to {app}_Ui_VarRenderer_*
        if (!$ok && !empty($app)) {
            // fallback to legacy method (manual load)
            $class = __CLASS__ . '_' . $driver;
            include_once $GLOBALS['registry']->get('fileroot', $app) . '/lib/Ui/VarRenderer/' . $driver . '.php';
            $ok = class_exists($class);
        }

        if (!$ok) {
            throw new LogicException('Class definition of ' . $class . ' not found.');
        }

        return new $class($params);
    }

    /**
     * Renders a variable.
     *
     * @param Horde_Form $form          A Horde_Form instance,
     *                                  or null if none is available.
     * @param Horde_Form_Variable $var  A Horde_Form_Variable.
     * @param Variables $vars           A Horde_Variables instance.
     * @param boolean $isInput          Whether this is an input field.
     */
    public function render($form, $var, $vars, $isInput = false)
    {
        $state = '_renderVar' . ($isInput ? 'Input' : 'Display');
        $method = $state . '_' . $var->type->getTypeName();
        if (!method_exists($this, $method)) {
            $method = $state . 'Default';
        }

        return $this->$method($form, $var, $vars);
    }

    /**
     * Finishes rendering after all fields are output.
     *
     * @return string  TODO
     */
    public function renderEnd()
    {
        return '';
    }
}
