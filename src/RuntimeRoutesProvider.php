<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core;

use Horde\Core\Config\RegistryState;
use Horde\Core\Middleware\DefaultStack;
use Horde\Core\Uri\RoutesProvider;
use Horde\Http\Uri;
use Horde\Routes\GroupMapper;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Runtime routes provider — loads and serves routes from all registered apps.
 *
 * Used in developer/debug mode (when var/config/use_compiled_router is absent).
 * In production, the compiled route cache is used instead via CompiledMatcher.
 *
 * Implements RoutesProvider so that RouteUrlWriter can generate URLs from
 * named routes without coupling to the runtime-vs-compiled distinction.
 *
 * Group context (prefix, host, scheme, port, defaults) is applied to each app's
 * routes at definition time. The resulting Route objects are fully self-contained.
 */
class RuntimeRoutesProvider extends GroupMapper implements RoutesProvider
{
    public function __construct(
        private readonly RegistryState $registryState,
        private readonly ServerRequestInterface $request,
    ) {
        parent::__construct();

        // Populate environ from PSR-7 request so Route::match() can check host/scheme/port
        $uri = $request->getUri();
        $host = $uri->getHost();
        $port = $uri->getPort();
        $this->environ['HTTP_HOST'] = $port ? $host . ':' . $port : $host;
        $this->environ['SERVER_NAME'] = $host;
        $this->environ['REQUEST_METHOD'] = $request->getMethod();
        $scheme = $uri->getScheme();
        if ($scheme === 'https') {
            $this->environ['HTTPS'] = 'on';
        }
    }

    /**
     * Load routes from all active apps in RegistryState.
     */
    public function loadAllApps(): void
    {
        $this->setDefaultStack(DefaultStack::get());

        $configBase = defined('HORDE_CONFIG_BASE') ? HORDE_CONFIG_BASE : '';

        foreach ($this->registryState->toArray() as $app => $config) {
            $status = $config['status'] ?? 'active';
            if (!in_array($status, ['active', 'notoolbar', 'hidden', 'admin'])) {
                continue;
            }

            $fileroot = $config['fileroot'] ?? null;
            $webroot = $config['webroot'] ?? null;
            if (!$fileroot || !$webroot) {
                continue;
            }

            $routeFile = $fileroot . '/config/routes.php';
            if (!file_exists($routeFile)) {
                continue;
            }

            $uri = new Uri($webroot);

            $this->group([
                'prefix' => rtrim($uri->getPath(), '/'),
                'host' => $uri->getHost() !== '' ? $uri->getHost() : null,
                'scheme' => $uri->getScheme() !== '' ? $uri->getScheme() : null,
                'port' => $uri->getPort(),
                'defaults' => ['app' => $app],
            ], function (GroupMapper $m) use ($routeFile, $fileroot, $configBase, $app) {
                $mapper = $m;
                include $routeFile;

                // Local overrides (admin-provided)
                if ($configBase !== '') {
                    $localRouteFile = $configBase . '/' . $app . '/routes.local.php';
                    if (file_exists($localRouteFile)) {
                        include $localRouteFile;
                    }
                }

                $inAppLocal = $fileroot . '/config/routes.local.php';
                if (file_exists($inAppLocal)) {
                    include $inAppLocal;
                }
            });
        }

        // Global admin routes (root group, no app prefix)
        if ($configBase !== '') {
            $globalRoutes = $configBase . '/routes.php';
            if (file_exists($globalRoutes)) {
                $mapper = $this;
                include $globalRoutes;
            }
        }

        $this->registerSystemRoutes();
        $this->compile();
    }

    public function generateNamedPath(string $routeName, array $params = []): ?string
    {
        $route = $this->getRouteNames()[$routeName] ?? null;
        if ($route === null) {
            return null;
        }
        return $route->generate($params);
    }

    /**
     * Register system named routes for app webroots, jsuri, and staticuri.
     *
     * These routes exist purely for URL generation (no controller, no middleware).
     * They are registered after route files so that app-defined routes take precedence.
     */
    private function registerSystemRoutes(): void
    {
        $existingNames = $this->getRouteNames();

        foreach ($this->registryState->toArray() as $app => $config) {
            $status = $config['status'] ?? 'active';
            if (!in_array($status, ['active', 'notoolbar', 'hidden', 'admin'])) {
                continue;
            }

            $webroot = $config['webroot'] ?? null;
            if (!$webroot) {
                continue;
            }

            $appPascal = ucfirst($app);
            $webrootPath = rtrim((new Uri($webroot))->getPath(), '/');

            // App webroot: e.g. "ImpHome" → /imp
            $webrootName = $appPascal . 'Home';
            if (!isset($existingNames[$webrootName])) {
                $this->buildRoute(uri: $webrootPath, name: $webrootName)->add();
            }

            // App jsuri: e.g. "ImpJs" → /imp/js
            $jsName = $appPascal . 'Js';
            if (!isset($existingNames[$jsName])) {
                $jsPath = $config['jsuri'] ?? $webrootPath . '/js';
                $this->buildRoute(uri: $jsPath, name: $jsName)->add();
            }
        }

        // Horde static URI
        $hordeConfig = $this->registryState->getApplication('horde');
        if ($hordeConfig && !isset($existingNames['HordeStatic'])) {
            $hordeWebroot = rtrim((new Uri($hordeConfig['webroot'] ?? '/horde'))->getPath(), '/');
            $staticPath = $hordeConfig['staticuri'] ?? $hordeWebroot . '/static';
            $this->buildRoute(uri: $staticPath, name: 'HordeStatic')->add();
        }
    }
}
