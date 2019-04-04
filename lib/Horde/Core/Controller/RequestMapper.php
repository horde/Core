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
     * @return string  App path if the app fits or empty string if not
     */
    protected function _resolveApp($registry, $app, $request, $requestServer,
        $hordeRoot = '')
    {
        $webroot = parse_url($registry->get('webroot', $app));
        // Filter out absolute urls unless they fit
        if (!empty($webroot['host'])) {
            if ($webroot['host'] != $requestServer) {
                return '';
            }
            // treat path as absolute if domain is given
            $normalized = $this->_normalize($webroot['path']);
            if ($this->beginsWith($request->getPath(), $normalized)) {
                return $normalized;
            }
        }

        // Relative to horde
        $normalized = $this->_normalize($hordeRoot . $webroot['path']);
        if ($this->_beginsWith($request->getPath(), $normalized)) {
            return $normalized;
        }
        return '';
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

        $apps = $registry->listApps();
        array_shift($apps);
        // reserve horde case for last
        $hordeRoot = parse_url($registry->get('webroot', 'horde'));
        foreach ($apps as $app) {
            $prefix = $this->_resolveApp($registry, $app, $request,
                $requestServer, $hordeRoot['path']);
            if ($prefix) {
                $foundApp = $app;
                break;
            }
        }
        if (empty($foundApp)) {
            $prefix = $this->_resolveApp($registry, 'horde', $request,
                $requestServer);
                if ($prefix) {
                $foundApp = 'horde';
            } else {
                $config->setControllerName('Horde_Core_Controller_NotFound');
                return $config;
            }
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

        return $config;
    }
}
