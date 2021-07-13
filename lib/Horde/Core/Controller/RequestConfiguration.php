<?php
/**
 * Copyright 2009-2020 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

/**
 * The Horde_Core_Controller_RequestConfiguration class provides 
 * information from the request to identify the Controller.
 * 
 * Copyright 2009-2020 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @package  Package
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 */
class Horde_Core_Controller_RequestConfiguration implements Horde_Controller_RequestConfiguration
{
    /**
     */
    protected $_classNames = array();

    /**
     */
    protected $_application;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_classNames = array(
            'controller' => 'Horde_Core_Controller_NotFound',
            'settings'   => 'Horde_Controller_SettingsExporter_Default',
        );
    }

    /**
     * Store the application which should handle the request
     *
     * @param string $application The application identifier
     *
     * @return void
     */
    public function setApplication($application)
    {
        $this->_application = $application;
    }

    /**
     * Return the application which should handle the request
     *
     * @return string The application identifier
     */
    public function getApplication()
    {
        return $this->_application;
    }

    /**
     * Store the application which should handle the request
     *
     * @param string $controllerName The controller class name
     *
     * @return void
     */
    public function setControllerName($controllerName)
    {
        $this->_classNames['controller'] = $controllerName;
    }

    /**
     * Return the controller class name
     *
     * @return string The controller name
     */
    public function getControllerName()
    {
        return $this->_classNames['controller'];
    }

    /**
     * Store the settings exporter class name
     *
     * @param string $settingsName The exporter class name
     *
     * @return void
     */
    public function setSettingsExporterName($settingsName)
    {
        $this->_classNames['settings'] = $settingsName;
    }

    /**
     * Return the settings exporter class name
     *
     * @return string The exporter class name
     */
    public function getSettingsExporterName()
    {
        return $this->_classNames['settings'];
    }
}
