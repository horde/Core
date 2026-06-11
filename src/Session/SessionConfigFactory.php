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

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\Injector\Injector;

/**
 * DI factory for {@see SessionConfig}.
 *
 * Reads `$conf['session']` and `$conf['cookie']` keys via
 * {@see ConfigLoader}, applies the same defaults the legacy
 * `Horde_Session` shim and {@see SessionLifecycle} use today, and
 * produces an immutable typed view.
 *
 * Tests that need a different config shape can construct
 * {@see SessionConfig} directly without going through this factory.
 */
class SessionConfigFactory
{
    /**
     * Build the per-request {@see SessionConfig}.
     */
    public function create(Injector $injector): SessionConfig
    {
        $loader = $injector->getInstance(ConfigLoader::class);
        $state = $loader->load('horde');

        return $this->fromState($state);
    }

    /**
     * Build a {@see SessionConfig} from a loaded {@see State}.
     *
     * Exposed separately so tests can hand-build a `State` and verify
     * the field-by-field translation without an injector.
     */
    public function fromState(State $state): SessionConfig
    {
        $cookieName = (string) ($state->get('session.name', '') ?? '');
        $cookieDomainRaw = (string) ($state->get('cookie.domain', '') ?? '');
        $cookiePath = (string) ($state->get('cookie.path', '') ?? '');
        if ($cookiePath === '') {
            $cookiePath = SessionConfig::DEFAULT_COOKIE_PATH;
        }
        $secure = ((int) ($state->get('use_ssl', 0) ?? 0)) === 1;
        $lifetime = (int) ($state->get('session.timeout', 0) ?? 0);

        $regenerate = $state->get('session.regenerate_interval');
        $regenerateInterval = is_int($regenerate) && $regenerate > 0
            ? $regenerate
            : SessionConfig::DEFAULT_REGENERATE_INTERVAL;

        $limiter = $state->get('session.cache_limiter');
        $cacheLimiter = (is_string($limiter) && $limiter !== '') ? $limiter : null;

        return new SessionConfig(
            cookieName: $cookieName,
            cookieDomain: $cookieDomainRaw === '' ? null : $cookieDomainRaw,
            cookiePath: $cookiePath,
            secure: $secure,
            lifetime: $lifetime,
            regenerateInterval: $regenerateInterval,
            cacheLimiter: $cacheLimiter,
        );
    }
}
