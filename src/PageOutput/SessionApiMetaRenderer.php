<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\PageOutput;

use Horde\Core\Config\RegistryState;
use Horde\Core\Session\HordeSession;
use Horde\Token\Token;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Render the `<meta>` tags that bootstrap the modern session API for
 * client-side JS.
 *
 * Emits two tags:
 *
 *   <meta name="session-api" content="https://webmail.example.com/horde/api/v1">
 *   <meta name="csrf-api"    content="<initial CSRF token>">
 *
 * The values are derived from {@see RegistryState} (NOT
 * {@see \Horde_Registry}) plus the active PSR-7 request and the active
 * {@see HordeSession}:
 *
 * - The base URL composes the request's scheme + host + (non-default)
 *   port with the `horde` app's `webroot` from `RegistryState` and the
 *   constant API path `/api/v1`. Cross-subdomain deployments serving
 *   `webmail.example.com/calendar.example.com` get the right host
 *   automatically because the request carries it. Non-standard ports
 *   (`:8443`, `:8080`) are preserved.
 *
 * - The CSRF token is minted via {@see Token::generate()} bound to
 *   {@see HordeSession::CSRF_SEED}. The {@see Token} service's storage
 *   guarantees a freshly-minted token validates on the next
 *   state-changing request.
 *
 * Anonymous sessions are emitted just like authenticated ones; anonymous
 * forms need CSRF protection too.
 *
 * The helper itself touches no globals. Legacy `Horde_PageOutput`
 * resolves it via the global injector (`$GLOBALS['injector']`) but the
 * resolution stays in the legacy caller; this class only depends on
 * what it gets in the constructor. Modern PSR-15 page-rendering
 * controllers inject this directly and call {@see addToCollector()}
 * with their request-attribute session.
 *
 * @see AssetCollector::addMetaTag()
 * @see \Horde\Base\Js\SessionApiClient.fromMeta() (consumer side)
 */
final class SessionApiMetaRenderer
{
    public const META_SESSION_API = 'session-api';
    public const META_CSRF_API = 'csrf-api';

    private const API_PATH = '/api/v1';

    public function __construct(
        private readonly RegistryState $registryState,
        private readonly Token $tokenService,
        private readonly ?ServerRequestInterface $request = null,
    ) {}

    /**
     * Render both meta tags as a single HTML string.
     *
     * Returns an empty string when no session is available; without a
     * session there is no CSRF token to mint and the client has no use
     * for a half-populated bootstrap.
     */
    public function renderTags(?HordeSession $session): string
    {
        if ($session === null) {
            return '';
        }

        $sessionApi = $this->getSessionApiUrl();
        $csrfApi = $this->mintCsrfToken($session);

        return $this->renderMeta(self::META_SESSION_API, $sessionApi)
            . $this->renderMeta(self::META_CSRF_API, $csrfApi);
    }

    /**
     * Add both meta tags to a modern {@see AssetCollector}.
     *
     * No-op when no session is available.
     */
    public function addToCollector(AssetCollector $collector, ?HordeSession $session): void
    {
        if ($session === null) {
            return;
        }

        $collector->addMetaTag(self::META_SESSION_API, $this->getSessionApiUrl(), httpEquiv: false);
        $collector->addMetaTag(self::META_CSRF_API, $this->mintCsrfToken($session), httpEquiv: false);
    }

    /**
     * Compose the absolute API URL.
     *
     * Public accessor so legacy callers (Horde_PageOutput) can route
     * the value through their own accumulator without going through
     * renderTags() / addToCollector(). Modern callers usually prefer
     * the bulk methods above.
     *
     * Pulls scheme + host + non-default port from the active request,
     * the `horde` app's `webroot` from {@see RegistryState}, and the
     * fixed API path. Falls back to a relative URL (webroot + path)
     * when no request is available (CLI rendering, tests).
     */
    public function getSessionApiUrl(): string
    {
        $webroot = $this->hordeWebroot();
        $path = rtrim($webroot, '/') . self::API_PATH;

        if ($this->request === null) {
            return $path;
        }

        $uri = $this->request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        if ($scheme === '' || $host === '') {
            return $path;
        }

        $absolute = $scheme . '://' . $host;
        $port = $uri->getPort();
        if ($port !== null && !$this->isDefaultPort($scheme, $port)) {
            $absolute .= ':' . $port;
        }
        return $absolute . $path;
    }

    /**
     * Mint a fresh CSRF token bound to the session-CSRF seed.
     *
     * Public accessor so legacy callers can fetch a token without
     * going through renderTags() / addToCollector(). Returns null when
     * no session is given so callers can omit the tag rather than
     * emit an empty value.
     */
    public function mintCsrfToken(?HordeSession $session): ?string
    {
        if ($session === null) {
            return null;
        }
        return $this->tokenService->generate(HordeSession::CSRF_SEED)->token;
    }

    /**
     * Resolve the horde app's webroot from {@see RegistryState}.
     *
     * Defaults to `/horde` when the app is missing or has no webroot
     * key. The fallback preserves the most common operator deployment
     * shape, but a missing horde app entry indicates a misconfiguration
     * worth fixing rather than papering over with silent defaults — the
     * fallback's role is to keep meta-tag rendering working while the
     * operator is diagnosing.
     */
    private function hordeWebroot(): string
    {
        $app = $this->registryState->getApplication('horde');
        if ($app === null) {
            return '/horde';
        }
        $webroot = $app['webroot'] ?? '/horde';
        return is_string($webroot) && $webroot !== '' ? $webroot : '/horde';
    }

    /**
     * Render a single `<meta name="..." content="...">` tag.
     *
     * Uses the `name=` attribute (not `http-equiv=`) for both tags;
     * these are not HTTP-equivalent directives.
     */
    private function renderMeta(string $name, string $content): string
    {
        return '<meta name="'
            . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" content="'
            . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . "\" />\n";
    }

    /**
     * Whether the given port is the scheme's default and should be
     * omitted from the rendered URL.
     */
    private function isDefaultPort(string $scheme, int $port): bool
    {
        return match (strtolower($scheme)) {
            'http' => $port === 80,
            'https' => $port === 443,
            default => false,
        };
    }
}
