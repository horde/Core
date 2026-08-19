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

use Horde\Core\Config\State;
use Horde\Core\Config\RegistryState;
use Horde\Http\Uri;
use Horde\Url\Psr7Bridge;
use Horde\Url\Url;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

class UriBuilder extends Uri implements UriBuilderInterface
{
    /**
     * $conf['use_ssl'] modes, mirroring Horde\Core\Horde::SSL_*.
     */
    private const SSL_NEVER = 0;
    private const SSL_ALWAYS = 1;
    private const SSL_AUTO = 2;

    private const STANDARD_PORTS = ['http' => 80, 'https' => 443];

    private RegistryState $registryState;
    private RouteMapperProvider $routeProvider;
    private ?State $configState;

    public function __construct(
        RegistryState $registryState,
        RouteMapperProvider $routeProvider,
        ?ServerRequestInterface $request = null,
        string $uri = '',
        ?State $configState = null,
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
        $this->configState = $configState;
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
        return $this->applyBase($this->resolveKey($app, 'webroot', '/' . $app));
    }

    public function withThemesUri(string $app): static
    {
        $appConfig = $this->requireApp($app);
        if (isset($appConfig['themesuri'])) {
            return $this->applyBase($appConfig['themesuri']);
        }
        $webroot = $appConfig['webroot'] ?? ('/' . $app);
        return $this->applyBase($webroot)->withPart('themes');
    }

    public function withJsUri(string $app): static
    {
        $appConfig = $this->requireApp($app);
        if (isset($appConfig['jsuri'])) {
            return $this->applyBase($appConfig['jsuri']);
        }
        $webroot = $appConfig['webroot'] ?? ('/' . $app);
        return $this->applyBase($webroot)->withPart('js');
    }

    public function withStaticUri(): static
    {
        $hordeConfig = $this->requireApp('horde');
        if (isset($hordeConfig['staticuri'])) {
            return $this->applyBase($hordeConfig['staticuri']);
        }
        $webroot = $hordeConfig['webroot'] ?? '/horde';
        return $this->applyBase($webroot)->withPart('static');
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

    public function withQueryParams(array $params): static
    {
        return $this->withQuery(http_build_query($params));
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

    /**
     * Apply a configured base to the builder.
     *
     * Webroot/themesuri/jsuri/staticuri values may be either a path
     * ("/horde") or a fully qualified URL ("https://assets.example.com/horde",
     * documented for proxy/asset-host deployments). A path replaces only
     * the path component; an absolute URL replaces scheme, userinfo, host,
     * port and path so the configured asset host wins over the request host.
     */
    private function applyBase(string $base): static
    {
        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $base) !== 1) {
            return $this->applyConfiguredAuthority()->withPath($base);
        }

        $parsed = new Uri($base);
        $clone = $this->withScheme($parsed->getScheme())
            ->withHost($parsed->getHost());

        $port = $parsed->getPort();
        $clone = $port !== null ? $clone->withPort($port) : $clone->withPort(null);

        $userInfo = $parsed->getUserInfo();
        if ($userInfo !== '') {
            [$user, $pass] = array_pad(explode(':', $userInfo, 2), 2, null);
            $clone = $clone->withUserInfo($user, $pass);
        }

        return $clone->withPath($parsed->getPath());
    }

    /**
     * Reconcile scheme/host/port against conf.php for a path-only base.
     *
     * When a registry value is path-only (no fully qualified base URL), the
     * absolute components come from conf.php, mirroring legacy Horde::url():
     *
     *   - `use_ssl = SSL_ALWAYS` forces https; `SSL_NEVER` forces http;
     *     `SSL_AUTO` (and any other value) leaves the request-derived scheme
     *     alone so the request has the last word.
     *   - `server.name` supplies the host in every mode when set.
     *   - `server.port` supplies the port, but a port that is standard for the
     *     resolved scheme (80/443) is dropped so it never renders.
     *
     * Without an injected config state the builder keeps its prior behaviour
     * and reflects only the incoming request.
     */
    private function applyConfiguredAuthority(): static
    {
        if ($this->configState === null) {
            return $this;
        }

        $clone = $this;

        $useSsl = (int) ($this->configState->get('use_ssl', self::SSL_NEVER) ?? self::SSL_NEVER);
        $schemeForced = false;
        if ($useSsl === self::SSL_ALWAYS) {
            $clone = $clone->withScheme('https');
            $schemeForced = true;
        } elseif ($useSsl === self::SSL_NEVER) {
            $clone = $clone->withScheme('http');
            $schemeForced = true;
        }

        $serverName = (string) ($this->configState->get('server.name', '') ?? '');
        if ($serverName !== '') {
            $clone = $clone->withHost($serverName);
        }

        $serverPort = $this->configState->get('server.port');
        if ($serverPort !== null && $serverPort !== '') {
            $clone = $clone->withPort((int) $serverPort);
        } elseif ($schemeForced) {
            // The request-inherited port belonged to the request scheme, which
            // config has just overridden; without a configured port it is
            // meaningless for the forced scheme, so drop it.
            $clone = $clone->withPort(null);
        }

        return $clone->stripStandardPort();
    }

    /**
     * Drop the port when it is the standard port for the current scheme.
     */
    private function stripStandardPort(): static
    {
        $port = $this->getPort();
        if ($port === null) {
            return $this;
        }
        $scheme = $this->getScheme();
        if (isset(self::STANDARD_PORTS[$scheme]) && self::STANDARD_PORTS[$scheme] === $port) {
            return $this->withPort(null);
        }

        return $this;
    }

    private static function normalizePath(string $path): string
    {
        return (string) preg_replace('#/{2,}#', '/', $path);
    }
}
