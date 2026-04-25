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

namespace Horde\Core\Uri;

use Horde\Core\Config\RegistryState;
use Horde\Http\Uri;
use Horde\Url\Psr7Bridge;
use Horde\Url\Url;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

class UriBuilder extends Uri implements UriBuilderInterface
{
    private RegistryState $registryState;
    private RouteMapperProvider $routeProvider;

    public function __construct(
        RegistryState $registryState,
        RouteMapperProvider $routeProvider,
        ?ServerRequestInterface $request = null,
        string $uri = '',
    ) {
        if ($request !== null && $uri === '') {
            $requestUri = $request->getUri();
            $base = '';
            $scheme = $requestUri->getScheme();
            if ($scheme !== '') {
                $base = $scheme . ':';
            }
            $authority = $requestUri->getAuthority();
            if ($authority !== '') {
                $base .= '//' . $authority;
            }
            $uri = $base;
        }
        parent::__construct($uri);
        $this->registryState = $registryState;
        $this->routeProvider = $routeProvider;
    }

    // PSR-7 with* overrides — narrow return type from self to static

    public function withScheme($scheme): static
    {
        $clone = parent::withScheme($scheme);
        assert($clone instanceof static);
        return $clone;
    }

    public function withUserInfo($user, $password = null): static
    {
        $clone = parent::withUserInfo($user, $password);
        assert($clone instanceof static);
        return $clone;
    }

    public function withHost($host): static
    {
        $clone = parent::withHost($host);
        assert($clone instanceof static);
        return $clone;
    }

    public function withPort($port): static
    {
        $clone = parent::withPort($port);
        assert($clone instanceof static);
        return $clone;
    }

    public function withPath($path): static
    {
        $clone = parent::withPath(self::normalizePath($path));
        assert($clone instanceof static);
        return $clone;
    }

    public function withQuery($query): static
    {
        $clone = parent::withQuery($query);
        assert($clone instanceof static);
        return $clone;
    }

    public function withFragment($fragment): static
    {
        $clone = parent::withFragment($fragment);
        assert($clone instanceof static);
        return $clone;
    }

    // Builder-specific methods

    public function withAppWebroot(string $app): static
    {
        $webroot = $this->resolveKey($app, 'webroot', '/' . $app);
        return $this->withPath($webroot);
    }

    public function withThemesUri(string $app): static
    {
        $appConfig = $this->requireApp($app);
        $themesUri = $appConfig['themesuri']
            ?? ($appConfig['webroot'] ?? ('/' . $app)) . '/themes';
        return $this->withPath($themesUri);
    }

    public function withJsUri(string $app): static
    {
        $appConfig = $this->requireApp($app);
        $jsUri = $appConfig['jsuri']
            ?? ($appConfig['webroot'] ?? ('/' . $app)) . '/js';
        return $this->withPath($jsUri);
    }

    public function withStaticUri(): static
    {
        $hordeConfig = $this->requireApp('horde');
        $staticUri = $hordeConfig['staticuri']
            ?? ($hordeConfig['webroot'] ?? '/horde') . '/static';
        return $this->withPath($staticUri);
    }

    public function withNamedRoute(string $app, string $name, array $params = []): static
    {
        $mapper = $this->routeProvider->getMapper($app);
        if ($mapper === null) {
            throw new InvalidArgumentException("No route mapper for application: $app");
        }
        $route = $mapper->routeNames[$name] ?? null;
        if ($route === null) {
            throw new InvalidArgumentException("Unknown route '$name' in application '$app'");
        }
        $path = $mapper->generate([$route], $params);
        if ($path === null) {
            throw new InvalidArgumentException(
                "Could not generate URL for route '$name' with given params"
            );
        }
        return $this->withPath($path);
    }

    public function withSlug(string $slug): static
    {
        $currentPath = $this->getPath();
        $newPath = rtrim($currentPath, '/') . '/' . trim($slug, '/') . '/';
        return $this->withPath($newPath);
    }

    public function withPart(string $part): static
    {
        $currentPath = $this->getPath();
        $newPath = rtrim($currentPath, '/') . '/' . ltrim($part, '/');
        return $this->withPath($newPath);
    }

    public function toHordeUrl(): Url
    {
        return Psr7Bridge::fromPsr7($this);
    }

    private function requireApp(string $app): array
    {
        $appConfig = $this->registryState->getApplication($app);
        if ($appConfig === null) {
            throw new InvalidArgumentException("Unknown application: $app");
        }
        return $appConfig;
    }

    private function resolveKey(string $app, string $key, string $default): string
    {
        $appConfig = $this->requireApp($app);
        return $appConfig[$key] ?? $default;
    }

    private static function normalizePath(string $path): string
    {
        return (string) preg_replace('#/{2,}#', '/', $path);
    }
}
