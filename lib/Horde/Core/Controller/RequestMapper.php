<?php
/**
 * @category Horde
 * @package  Core
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
     * Check if an app is the relevant source of routes for a request
     *
     * The original approach was too specific for the default horde use
     * setup of an app always living below horde root
     * We also need to think about the case of different hosts
     *
     * @return array(string, string) app and  App path if the app fits
     */
    protected function _resolveApp(
        $registry,
        $app,
        $request,
        $requestServer,
        $hordeRoot = ''
    ) {
        $webroot = parse_url($registry->get('webroot', $app));
        // Filter out absolute urls with domains unless they fit
        if (!empty($webroot['host']) && ($webroot['host'] != $requestServer)) {
            return array('', '');
        }
        // Relative to webroot
        // This might be the case if the webroot is a horde app
        // horde is symlinked to /horde
        // and the other apps are relative to horde
        // Also path is absolute if domain is given
        $normalized = $this->_normalize($webroot['path']);
        if ($this->_beginsWith($request->getPath(), $normalized)) {
            return array($app, $normalized);
        }
        // Relative to horde
        $normalized = $this->_normalize($hordeRoot . $webroot['path']);
        if ($this->_beginsWith($request->getPath(), $normalized)) {
            return array($app, $normalized);
        }
        return array('', '');
    }

    protected function _beginsWith($subject, $prefix)
    {
        return substr($subject, 0, strlen($prefix)) == $prefix;
    }

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

    public function getRequestConfiguration(Horde_Injector $injector)
    {
        $request = $injector->getInstance('Horde_Controller_Request');
        $requestServer = $_SERVER['SERVER_NAME'];
        $registry = $injector->getInstance('Horde_Registry');
        $settingsFinder = $injector->getInstance('Horde_Core_Controller_SettingsFinder');

        $config = $injector->createInstance('Horde_Core_Controller_RequestConfiguration');
        // $registry->listApps() without params returns empty on unauthenticated access
        $apps = $registry->listApps(null, false, null);
        // reserve horde case for last
        $hordeRoot = parse_url($registry->get('webroot', 'horde'));
        foreach ($apps as $app) {
            list($foundApp, $prefix) = $this->_resolveApp(
                $registry,
                $app,
                $request,
                $requestServer,
                $hordeRoot['path']
            );
            if ($foundApp || $prefix) {
                $foundApp = $app;
                break;
            }
        }
        // If we found no app
        // Or found an app which lives in webroot
        // we need to check if horde may fit
        if (empty($foundApp) || empty($prefix) || $prefix == '/') {
            list($foundHorde, $prefixHorde) =
            $this->_resolveApp($registry, 'horde', $request, $requestServer);
            if ($foundHorde) {
                $foundApp = 'horde';
                $prefix = $prefixHorde;
            }
        }

        // If we still found no app, give up
        if (empty($foundApp)) {
            $config->setControllerName('Horde_Core_Controller_NotFound');
            return $config;
        }
        // Route mapper doesn't like / as prefix
        if ($prefix == '/') {
            $prefix = '';
        }

        $config->setApplication($foundApp);
        $app = $foundApp;
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

            // Default behaviour should be to authenticate as older controllers expect it. This should be overrideable
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
