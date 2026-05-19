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
use Horde\Routes\FluentRouteBuilder;
use Horde\Routes\Mapper;
use Horde\Routes\Route;
use Horde\Routes\RouteBuilder;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Global route mapper that pre-loads routes from all registered apps.
 *
 * All routes live in a single flat matchList/routeNames. Each app's webroot
 * is parsed into per-route host/scheme/port/pathPrefix via overridden
 * buildRoute() and connect() methods. Named route lookup works across all apps.
 */
class RuntimeRoutesMapper extends Mapper
{
    private string $currentAppPrefix = '';
    private ?string $currentAppHost = null;
    private ?string $currentAppScheme = null;
    private ?int $currentAppPort = null;
    private string $currentApp = '';

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
     * Override buildRoute to inject the current app's base URI into each route.
     */
    public function buildRoute(?string $uri = null, ?string $name = null): FluentRouteBuilder
    {
        $fullUri = $this->currentAppPrefix . ($uri ?? '');
        $builder = parent::buildRoute($fullUri, $name);

        if ($this->currentAppHost !== null) {
            $builder->withHost($this->currentAppHost);
        }
        if ($this->currentAppScheme !== null) {
            $builder->withScheme($this->currentAppScheme);
        }
        if ($this->currentAppPort !== null) {
            $builder->withPort($this->currentAppPort);
        }

        $builder->withDefaults(['app' => $this->currentApp]);

        return $builder;
    }

    /**
     * Override connect to inject the current app's base URI into legacy routes.
     */
    public function connect($first, $second = null, $third = null)
    {
        // Replicate parent's arg parsing to find routePath and kargs
        if ($third !== null) {
            $routeName = $first;
            $routePath = $second;
            $kargs = $third;
        } elseif ($second !== null) {
            if (is_array($second)) {
                $routeName = null;
                $routePath = $first;
                $kargs = $second;
            } else {
                $routeName = $first;
                $routePath = $second;
                $kargs = [];
            }
        } else {
            $routeName = null;
            $routePath = $first;
            $kargs = [];
        }

        // Inject app's base URI
        $routePath = $this->currentAppPrefix . $routePath;
        $kargs['app'] = $kargs['app'] ?? $this->currentApp;

        if ($this->currentAppHost !== null && !isset($kargs['_host'])) {
            $kargs['_host'] = $this->currentAppHost;
        }
        if ($this->currentAppScheme !== null && !isset($kargs['_scheme'])) {
            $kargs['_scheme'] = $this->currentAppScheme;
        }
        if ($this->currentAppPort !== null && !isset($kargs['_port'])) {
            $kargs['_port'] = $this->currentAppPort;
        }

        // Call parent with normalized 3-arg form
        if ($routeName !== null) {
            parent::connect($routeName, $routePath, $kargs);
        } else {
            parent::connect($routePath, $kargs);
        }
    }

    /**
     * Prefix secondary route paths with the current app webroot.
     *
     * buildRoute() already prefixes the primary URI; secondary paths added via
     * withSecondaryRoute() are app-relative and need the same prefix for
     * routematch(). We build the RouteBuilder here, prefix secondary Route
     * objects, and pass the final array to the parent.
     */
    public function addRoute(RouteBuilder|Route|array $routeOrBuilder): void
    {
        if ($this->currentAppPrefix === '') {
            parent::addRoute($routeOrBuilder);
            return;
        }

        if ($routeOrBuilder instanceof RouteBuilder) {
            $routeOrBuilder = $routeOrBuilder->build();
        }

        $routes = is_array($routeOrBuilder) ? $routeOrBuilder : [$routeOrBuilder];
        foreach ($routes as $route) {
            if ($route->secondary && !str_starts_with($route->routePath, $this->currentAppPrefix)) {
                $route->routePath = $this->currentAppPrefix . '/' . ltrim($route->routePath, '/');
            }
        }

        parent::addRoute($routes);
    }

    /**
     * Load routes from all active apps in RegistryState.
     */
    public function loadAllApps(): void
    {
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

            // Parse webroot into components
            $parsed = $this->parseWebroot($webroot);
            $this->currentAppPrefix = $parsed['path'];
            $this->currentAppHost = $parsed['host'];
            $this->currentAppScheme = $parsed['scheme'];
            $this->currentAppPort = $parsed['port'];
            $this->currentApp = $app;

            // routes.php calls $mapper->buildRoute() / $mapper->connect()
            $mapper = $this;
            include $routeFile;

            // Local overrides
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
        }

        // Reset state
        $this->currentAppPrefix = '';
        $this->currentAppHost = null;
        $this->currentAppScheme = null;
        $this->currentAppPort = null;
        $this->currentApp = '';

        // Build route regexps once
        $this->createRegs();
    }

    /**
     * Parse a webroot (path-only or full URL) into components.
     *
     * @return array{path: string, host: ?string, scheme: ?string, port: ?int}
     */
    private function parseWebroot(string $webroot): array
    {
        if (str_starts_with($webroot, 'http://') || str_starts_with($webroot, 'https://')) {
            $parsed = parse_url($webroot);
            return [
                'path' => rtrim($parsed['path'] ?? '', '/'),
                'host' => $parsed['host'] ?? null,
                'scheme' => $parsed['scheme'] ?? null,
                'port' => isset($parsed['port']) ? (int) $parsed['port'] : null,
            ];
        }

        return [
            'path' => rtrim($webroot, '/'),
            'host' => null,
            'scheme' => null,
            'port' => null,
        ];
    }
}
