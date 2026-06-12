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

namespace Horde\Core\Session;

use Horde\Core\Session\SessionConfigFactory;
use Horde\Injector\Attribute\Factory;

/**
 * Immutable typed view of session-related Horde configuration.
 *
 * Centralises the `$conf['session']` and `$conf['cookie']` keys that
 * lifecycle and middleware code reads. Built by
 * {@see SessionConfigFactory} from the same `ConfigLoader` state
 * {@see SessionHandlerFactory} uses, so backend and front-end agree on
 * one source.
 *
 * The class is a pure value object: no behaviour, no mutation. Callers
 * read the readonly properties directly. A future middleware layer
 * consumes this in place of repeated `$conf['session'][*]` /
 * `$conf['cookie'][*]` lookups across the request lifecycle.
 *
 * Defaults match what the legacy `Horde_Session` shim and
 * {@see SessionLifecycle} apply today when the corresponding config
 * key is absent or empty.
 */
#[Factory(factory: SessionConfigFactory::class, method: 'create')]
final class SessionConfig
{
    /** Default forced rotation interval in seconds (6 hours). */
    public const DEFAULT_REGENERATE_INTERVAL = 21600;

    /** Default cookie path (root). */
    public const DEFAULT_COOKIE_PATH = '/';

    /**
     * @param string      $cookieName         Cookie name carrying the session id.
     *                                        From `$conf['session']['name']`.
     * @param string|null $cookieDomain       Cookie Domain attribute. Null
     *                                        scopes the cookie to the exact
     *                                        request host. From
     *                                        `$conf['cookie']['domain']`.
     * @param string      $cookiePath         Cookie Path attribute. From
     *                                        `$conf['cookie']['path']`,
     *                                        defaults to `/`.
     * @param bool        $secure             Whether the Secure attribute
     *                                        is set on the cookie. From
     *                                        `$conf['use_ssl'] == 1`.
     * @param int         $lifetime           Cookie / session lifetime in
     *                                        seconds. 0 = browser session
     *                                        (cookie expires when browser
     *                                        closes). From
     *                                        `$conf['session']['timeout']`.
     * @param int         $regenerateInterval Forced session ID rotation
     *                                        interval in seconds. From
     *                                        `$conf['session']['regenerate_interval']`,
     *                                        defaults to
     *                                        {@see DEFAULT_REGENERATE_INTERVAL}.
     * @param string|null $cacheLimiter       PHP `session_cache_limiter()`
     *                                        value. Null leaves the PHP
     *                                        default in place. From
     *                                        `$conf['session']['cache_limiter']`.
     * @param string      $serverName         Hostname the application is
     *                                        served under. Used by the
     *                                        cookie-domain guard in
     *                                        {@see SessionLifecycle::setup()}
     *                                        to refuse the broken
     *                                        single-label-host plus
     *                                        Domain-attribute combination.
     *                                        From `$conf['server']['name']`.
     * @param bool        $cookieDisabled     When true,
     *                                        {@see HordeSessionMiddleware}
     *                                        does NOT emit `Set-Cookie` on
     *                                        any of its lifecycle paths
     *                                        (steady-state, regenerated,
     *                                        destroyed). Used by pure API
     *                                        routes that identify clients
     *                                        via `Authorization: Bearer`
     *                                        tokens and do not want a
     *                                        session cookie. The session
     *                                        row is still loaded and saved
     *                                        through {@see SessionHandler};
     *                                        only the cookie transport
     *                                        layer is suppressed. Default
     *                                        false (cookie path active).
     */
    public function __construct(
        public readonly string $cookieName,
        public readonly ?string $cookieDomain,
        public readonly string $cookiePath,
        public readonly bool $secure,
        public readonly int $lifetime,
        public readonly int $regenerateInterval,
        public readonly ?string $cacheLimiter,
        public readonly string $serverName = '',
        public readonly bool $cookieDisabled = false,
    ) {}
}
