<?php
/**
 * Copyright 2009-2020 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael J Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

/**
 * The Horde_Core_Controller_RequestMapper class provides 
 * logic to identify which app is supposed to handle the request.
 *
 * It loads the relevant routes file.
 * The routes file may either demand a certain type of authentication
 * or restrict access to certain HTTP verbs.
 *
 * Supported auth types:
 * - default: The user is supposed to be already logged in and has a session
 * - basic: The request comes with HTTP BASIC AUTH credentials
 * - none: Unauthenticated access is permitted
 *
 * Finding the right app is supposed to work even if the app does not live
 * below the horde base URL or even on a separate domain as long as
 * it is properly configured in the registry.
 *
 * Copyright 2009-2020 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Michael J Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package  Package
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 */
class Horde_Core_Controller_RequestMapper
{
    /**
     * @var Horde_Routes_Mapper $mapper
     */
    protected $_mapper;

    public function __construct(Horde_Routes_Mapper $mapper)
    {
        $this->_mapper = $mapper;
    }

    /**
     * Rebuild a path string to a common form
     *
     * Remove any . and .. levels and parts made irrelevant by them
     *
     * @param string $path The input path
     *
     * @return string The normalized path
     */
    protected function _normalize($path)
    {
        $partsIn = explode('/', $path);
        $partsOut = [];
        foreach ($partsIn as $part) {
            // useless slashes
            if (empty($part)) {
                continue;
            }
            // useless level
            if ($part == '.') {
                continue;
            }
            // one level up
            if ($part == '..') {
                array_pop($partsOut);
                continue;
            }
            $partsOut[] = $part;
        }
        return '/' . implode('/', $partsOut);
    }

    /**
     * Identify the correct app to handle the request
     *
     * This needs to cover a lot of cases
     * - app lives below horde (pear default)
     * - app lives besides or independent of horde (composer default)
     * - app is horde
     * - app lives in document root (https://webmail.foo.org is imp)
     * - app lives below horde but document root is another app
     *    eg https://webmail.foo.org where / is imp, /horde is horde, /horde/turba is turba
     *
     * @param string                   $scheme  Request URI scheme (https, http)
     * @param Horde_Controller_Request $host    Request host part (www.foo.org)
     * @param string $host    Request host part (www.foo.org)
     * @param Horde_Registry  $registry         The Horde Registry
     */
    protected function _identifyApp($scheme, $request, $host, $registry)
    {
        $matches = [];
        // listApps() would return empty on unauthenticated access
        foreach ($registry->listApps(null, false, null) as $app)
        {
            $default = [
               'scheme' => $scheme,
               'host' => $host,
               'path' => '',
               'app' => $app
            ];
            $applicationUrl = array_merge($default, parse_url($registry->get('webroot', $app)));
            $applicationUrl['path'] = $this->_normalize($applicationUrl['path']);
            // sort out cases with wrong host or scheme
            if ($scheme != $applicationUrl['scheme']) { continue; }
            if ($host != $applicationUrl['host']) { continue; }
            // does the path match at all?
            if (substr($request->getPath(),0, strlen($applicationUrl['path'])) == $applicationUrl['path']) {
                $matches[] = $applicationUrl;
            }
        }
        // No matches, return early
        if (count($matches) == 0) {
            return $matches;
        }
        // Longest match path *should* always be the right app
        usort($matches, function($a, $b) 
        {
             return strlen($a['path']) <=> strlen($b['path']);
        }
        );
        return array_pop($matches);
    }


    /**
     * Build the request configuration
     *
     * If no found app fits, return the NotFound controller.
     *
     * Initialize the found app.
     * Load the route definition file for the found app.
     *
     * Initialize the routes mapper with the app's base path and match
     *
     * Finally check authentication if required
     *
     * @param Horde_Injector $injector The Dependency Injector
     *
     * @return Horde_Controller_RequestConfiguration The found config
     */
    public function getRequestConfiguration(Horde_Injector $injector)
    {
        $request = $injector->getInstance('Horde_Controller_Request');
        $requestServer = $_SERVER['SERVER_NAME'];
        $uriScheme = $_SERVER['REQUEST_SCHEME'];
        $registry = $injector->getInstance('Horde_Registry');
        $settingsFinder = $injector->getInstance('Horde_Core_Controller_SettingsFinder');

        $config = $injector->createInstance('Horde_Core_Controller_RequestConfiguration');
        $found = $this->_identifyApp($uriScheme, $request, $requestServer, $GLOBALS['registry']);
        $prefix = $found['path'];

        // If we still found no app, give up

        if (empty($found)) {
            $config->setControllerName('Horde_Core_Controller_NotFound');
            return $config;
        }
        // Route mapper doesn't like / as prefix
        if ($prefix == '/') {
            $prefix = '';
        }

        $config->setApplication($found['app']);
        $app = $found['app'];
        // Check for route definitions.
        $fileroot = $registry->get('fileroot', $app);
        $routeFile = $fileroot . '/config/routes.php';
        if (!file_exists($routeFile)) {
            $config->setControllerName('Horde_Core_Controller_NotFound');
            return $config;
        }

        // Push $app onto the registry
        $registry->pushApp($app);
        // Application routes are relative only to the application. Let the
        // mapper know where they start.
        $this->_mapper->prefix = $prefix;
        // Set the application controller directory
        $this->_mapper->directory = $registry->get('fileroot', $app) . '/app/controllers';

        // Load application routes.
        $mapper = $this->_mapper;
        $mapper->environ = array('REQUEST_METHOD' => $request->getMethod());
        include $routeFile;
        if (file_exists($fileroot . '/config/routes.local.php')) {
            include $fileroot . '/config/routes.local.php';
        }
        // Match
        // @TODO Cache routes
        $path = $request->getPath();
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        $match = $this->_mapper->match($path);
        if (isset($match['controller'])) {
            $config->setControllerName(Horde_String::ucfirst($app) . '_' . Horde_String::ucfirst($match['controller']) . '_Controller');
            $config->setSettingsExporterName($settingsFinder->getSettingsExporterName($config->getControllerName()));
        } else {
            $config->setControllerName('Horde_Core_Controller_NotFound');
        }
        // TODO: Move to some ControllerAuthHelper and check perms and admin
        if (!$registry->isAuthenticated()) {
            $auth = $injector->getInstance('Horde_Core_Factory_Auth')->create();

            // Default behaviour should be to authenticate if none given.
            // Older controllers expect this.
            if (!isset($match['HordeAuthType'])) {
                $match['HordeAuthType'] = 'DEFAULT';
            }
            // Keep unauthenticated
            if ($match['HordeAuthType'] == 'NONE') {
                return $config;
            }
            if ($match['HordeAuthType'] == 'DEFAULT') {
                // Try to authenticate, otherwise redirect to login page
                // Check for basic auth
                if (isset($_SERVER['PHP_AUTH_USER']) and isset($_SERVER['PHP_AUTH_PW'])) {
                    $res = $auth->authenticate($_SERVER['PHP_AUTH_USER'], ['password' => $_SERVER['PHP_AUTH_PW']]);
                    if ($res) {
                        return $config;
                    }
                }
                $registry->getServiceLink('login');
                Horde::url($registry->getInitialPage('horde'))->redirect();
            }
            // In API mode, either allow a request
            if ($match['HordeAuthType'] == 'BASIC') {
                if ($auth->authenticate($_SERVER['PHP_AUTH_USER'], ['password' => $_SERVER['PHP_AUTH_PW']])) {
                    return $config;
                }
                $config->setControllerName('Horde_Core_Controller_NotAuthorized');
                return $config;
            }
        }

        return $config;
    }
}
