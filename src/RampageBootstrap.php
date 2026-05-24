<?php

declare(strict_types=1);

/**
 * Copyright 2024-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2024-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core;

use Composer\InstalledVersions;
use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Config\RegistryState;
use Horde\Core\Config\Vhost;
use Horde\Http\ResponseFactory;
use Horde\Http\Server\RampageRequestHandler;
use Horde\Http\Server\RequestBuilder;
use Horde\Http\Server\ResponseWriterWeb;
use Horde\Http\Server\Runner;
use Horde\Http\StreamFactory;
use Horde\Core\Uri\RoutesProvider;
use Horde\Injector\Injector;
use Horde\Injector\TopLevel;
use Horde\Routes\MatchResult;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Horde_Injector;

/**
 * Bootstrap the Rampage HTTP endpoint
 */
class RampageBootstrap
{
    public static function run(): void
    {
        $responseFactory = new ResponseFactory();
        $streamFactory = new StreamFactory();

        // 1. Create Injector
        $injector = new Injector(new TopLevel());
        $injector->setInstance(Injector::class, $injector);
        $injector->setInstance(Horde_Injector::class, $injector);

        if (class_exists('Horde\Bundle\PrecompiledBindings')) {
            (new \Horde\Bundle\PrecompiledBindings())->register($injector);
        } else {
            (new DefaultInjectorBindings())->register($injector);
        }

        // 2. Build ServerRequest
        $request = (new RequestBuilder())->withGlobalVariables()->build();
        $injector->setInstance(ServerRequestInterface::class, $request);

        // 3. Ensure HORDE_CONFIG_BASE is defined
        if (!defined('HORDE_CONFIG_BASE')) {
            define('HORDE_CONFIG_BASE', InstalledVersions::getRootPackage()['install_path'] . '/var/config');
        }

        // 4. Load RegistryState
        if (class_exists('Horde\Bundle\PrecompiledRegistry')) {
            $registryState = new \Horde\Bundle\PrecompiledRegistry();
        } else {
            $vendorBase = defined('HORDE_BASE')
                ? HORDE_BASE
                : InstalledVersions::getRootPackage()['install_path'];
            $registryState = (new RegistryConfigLoader(HORDE_CONFIG_BASE, $vendorBase, new Vhost()))->load();
        }
        $injector->setInstance(RegistryState::class, $registryState);

        // 5. RuntimeRoutesProvider — pre-load ALL app routes
        $runtimeMapper = new RuntimeRoutesProvider($registryState, $request);
        $runtimeMapper->loadAllApps();
        $injector->setInstance(RuntimeRoutesProvider::class, $runtimeMapper);
        $injector->setInstance(RoutesProvider::class, $runtimeMapper);

        // 6. Match route
        $path = $request->getUri()->getPath();
        $result = $runtimeMapper->routematch($path);

        if ($result === null) {
            http_response_code(404);
            echo 'No route matched: ' . htmlspecialchars($path);
            return;
        }

        [$matchDict, $route] = $result;
        $app = $matchDict['app'];
        $matchResult = new MatchResult($matchDict, $route, $route->routeName ?? '');
        $injector->setInstance(MatchResult::class, $matchResult);

        // Determine middleware stack and controller
        if (!isset($matchDict['stack']) && ($matchDict['HordeAuthType'] ?? null) === 'NONE') {
            $matchDict['stack'] = [];
        }
        $stack = $matchDict['stack'] ?? [];
        $controller = $matchDict['controller'] ?? '';

        // Set request attributes — formal contract with middlewares
        $request = $request->withAttribute('app', $app);
        $request = $request->withAttribute('matchResult', $matchResult);
        $request = $request->withAttribute('route', $matchDict);
        $request = $request->withAttribute('stack', $stack);
        $request = $request->withAttribute('controller', $controller);
        $request = $request->withAttribute('mapper', $runtimeMapper);

        // 7. Build handler — stack as class names, resolved lazily via injector
        $handler = new RampageRequestHandler(
            $responseFactory,
            $streamFactory,
            $stack,
            $controller !== '' ? $controller : null,
            $injector,
        );

        // 8. Run
        $runner = new Runner($handler, new ResponseWriterWeb());
        try {
            $runner->run($request);
        } catch (Throwable $e) {
            http_response_code(500);
            error_log((string) $e);
            if (getenv('HORDE_DEBUG')) {
                echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
            } else {
                echo 'Internal Server Error';
            }
        }
    }
}
