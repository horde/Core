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
 * The Horde_Core_Controller_SettingsFinder class provides 
 * logic to find the most appropriate SettingsExporter for a controller
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
class Horde_Core_Controller_SettingsFinder
{
    /**
     * Find the appropriate SettingsExporter's class name
     *
     * The default exporter is Horde_Controller_SettingsExporter_Default.
     * If a SettingsExporter with the same base name as the controller is
     * found, it is used instead. Direct and indirect parent classes of the
     * controller are also checked.
     *
     * @param string $controllerName The controller's class name
     *
     * @return string The SettingsExporter's class name
     */
    public function getSettingsExporterName($controllerName)
    {
        $current = $controllerName;
        while ($current && class_exists($current)) {
            $settingsName = $this->_mapName($current);
            if (class_exists($settingsName)) {
                return $settingsName;
            }

            $current = $this->_getParentName($current);
        }

        return 'Horde_Controller_SettingsExporter_Default';
    }

    /**
     * Derive the SettingsExporter class name from the controller class name
     *
     * @param string $controllerName The controller class name
     *
     * @return string The most relevant SettingsExporter class name found
     */
    private function _mapName($controllerName)
    {
        return str_replace('_Controller', '_SettingsExporter', $controllerName);
    }

    /**
     * Derive the parent class name from the controller class name
     *
     * @param string $controllerName The controller class name
     *
     * @return string|null The parent class name or null
     */
    private function _getParentName($controllerName)
    {
        $klass = new ReflectionClass($controllerName);
        $parent = $klass->getParentClass();
        return $parent ? $parent->name : null;
    }
}
