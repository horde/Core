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

use Horde\Core\Secret\SessionSecret;
use Horde\Injector\Attribute\Factory;
use Horde\Injector\Injector;
use Horde\SessionHandler\SessionHandler;
use Horde\SessionHandler\SessionId;
use Horde_Exception;
use Horde_Shutdown;
use Horde_Shutdown_Task;

/**
 * Orchestrates the lifecycle of the current PHP session under Horde control.
 *
 * Composes the modern {@see SessionHandler} (registered with PHP) and
 * {@see HordeSession} (the per-request data layer) plus Horde-application
 * glue: PHP ini tuning, the cookie-domain/single-label-hostname guard, the
 * shutdown task that mirrors {@see HordeSession} payload back into
 * `$_SESSION`, and optional {@see SessionSecret} re-keying on `clean()` /
 * `destroy()`.
 *
 * Registry-agnostic. SessionLifecycle is the engine both legacy and
 * modern flows leverage. It does not reach upward into Registry or
 * sideways into the shim.
 *
 * Two flavours of lifecycle methods coexist:
 *
 * - **Synchronous executors** {@see clean()}, {@see destroy()},
 *   {@see regenerate()}: act immediately, called when the caller knows
 *   the right moment. Each clears HordeSession's intent markers after
 *   acting so a downstream {@see processFlags()} call is a coherent
 *   no-op.
 *
 * - **Engine** {@see processFlags()}: reads the lifecycle intent
 *   markers carried by {@see HordeSession} and dispatches to the
 *   matching synchronous executor. Canonical reader of those markers.
 *   Other code paths SHOULD NOT read the markers directly. Always
 *   called explicitly; never auto-fired from {@see shutdown()} or
 *   any other implicit trigger. Lifecycle dispatch is visible at the
 *   call site, not magic last-minute behaviour.
 *
 * Time-based vs intent-based regeneration:
 *
 * - {@see regenerationDue()} is a time-deadline check ("the regenerate-at
 *   timestamp has passed"). Reads via {@see HordeSession}, not the
 *   superglobal.
 * - {@see HordeSession::shouldRegenerate()} is intent ("a controller
 *   asked for it"). Read by {@see processFlags()}.
 *
 * The two are independent and compose: a caller can rotate when either
 * fires.
 *
 * Replaces the lifecycle role previously carried by the legacy
 * `Horde_Session` shim. Modern callers (Registry bootstrap, LoginService,
 * Auth_Application, Ajax/Application) consume this class directly. The shim
 * keeps its public lifecycle methods as thin delegates for BC.
 *
 * The class deliberately does NOT do:
 * - Data reads/writes (use {@see HordeSession}).
 * - Token generation/verification (use {@see \Horde\Token\Token}).
 * - Offline session administration (use
 *   {@see \Horde\SessionHandler\SessionAdministrator}).
 * - The save-handler callback side (that lives on {@see SessionHandler}).
 */
#[Factory(factory: SessionLifecycleFactory::class, method: 'create')]
class SessionLifecycle implements Horde_Shutdown_Task
{
    /** Marker for a string scoped value (matches HordeSession). */
    private const NOT_SERIALIZED = "\0";

    /**
     * Whether PHP's session machinery is currently open for read/write.
     *
     * Mirrors PHP's session_status() but tracked locally so the lifecycle
     * shutdown task can branch on it cheaply.
     */
    private bool $active = false;

    /** Whether {@see clean()} has run this request. Idempotency guard. */
    private bool $cleaned = false;

    /** Whether {@see setup()} has installed the save handler this request. */
    private bool $handlerRegistered = false;

    /** Whether the request-shutdown mirror task is registered. */
    private bool $shutdownRegistered = false;

    /**
     * Whether the one-time setup work (ini tuning, cookie params,
     * cache_limiter, session_name, optional session_id forcing) has
     * already been applied this request. Guards against repeated
     * setup() calls overlapping the legacy shim and the modern
     * middleware on the same request.
     */
    private bool $setupApplied = false;

    /**
     * @param Injector              $injector Injector for resolving the
     *                                        current {@see HordeSession}
     *                                        on each call. The lifecycle
     *                                        does not cache the session
     *                                        because {@see clean()} and
     *                                        {@see destroy()} replace it.
     * @param SessionHandler        $handler  Modern save handler. Registered
     *                                        with PHP via setup().
     * @param SessionConfig         $config   Typed view of session-related
     *                                        Horde config keys. Built by
     *                                        {@see SessionConfigFactory} from
     *                                        the same `ConfigLoader` state
     *                                        {@see SessionHandlerFactory} uses.
     * @param SessionSecret|null    $secret   Optional. Re-keyed on clean(),
     *                                        cleared on destroy().
     */
    public function __construct(
        private readonly Injector $injector,
        private readonly SessionHandler $handler,
        private readonly SessionConfig $config,
        private readonly ?SessionSecret $secret = null,
    ) {}

    /**
     * Bootstrap the current PHP session under Horde control.
     *
     * Tunes PHP session ini settings, applies the cookie-domain / single-
     * label-hostname guard, registers the modern save handler with PHP,
     * registers a request-shutdown mirror task, and optionally calls
     * {@see start()}.
     *
     * Idempotent — repeat calls in the same request reapply the ini tuning
     * but do not re-register the handler or the shutdown task.
     *
     * @param bool        $start        Open the session immediately.
     * @param string|null $cacheLimiter Override for session.cache_limiter.
     *                                  Null falls back to conf.session.cache_limiter.
     * @param string|null $sessionId    Force a specific session ID.
     *
     * @throws Horde_Exception When the cookie-domain guard fails.
     */
    public function setup(
        bool $start = true,
        ?string $cacheLimiter = null,
        ?string $sessionId = null,
    ): void {
        if (!$this->setupApplied) {
            ini_set('url_rewriter.tags', 0);

            $cookieDomain = $this->config->cookieDomain ?? '';
            $serverName = $this->config->serverName;
            if ($cookieDomain !== '' && strpos($serverName, '.') === false) {
                throw new Horde_Exception(sprintf(
                    'Session cookies will not work because the server name "%s" '
                    . 'is a single-label hostname (no dot) but a cookie domain '
                    . '("%s") is configured. Browsers reject Domain= cookie '
                    . 'attributes on hostnames without a dot. This typically '
                    . 'affects http://localhost and other single-label hostnames. '
                    . 'Either: (1) use a fully qualified hostname like '
                    . 'http://horde.localhost or http://example.test, '
                    . '(2) clear $conf[\'cookie\'][\'domain\'] to let the browser '
                    . 'scope the cookie to the exact hostname, or '
                    . '(3) enable URL-based sessions by clearing '
                    . '$conf[\'session\'][\'use_only_cookies\'] (not recommended).',
                    $serverName,
                    $cookieDomain,
                ));
            }

            $timeout = $this->config->lifetime;
            if ($timeout > 0) {
                ini_set('session.gc_maxlifetime', (string) $timeout);
            }

            session_set_cookie_params(
                $timeout,
                $this->config->cookiePath,
                $cookieDomain,
                $this->config->secure,
                true,
            );
            session_cache_limiter(
                $cacheLimiter ?? ($this->config->cacheLimiter ?? ''),
            );
            session_name(urlencode($this->config->cookieName));
            if ($sessionId !== null && $sessionId !== '') {
                session_id($sessionId);
            }

            if (!$this->handlerRegistered) {
                session_set_save_handler($this->handler, true);
                $this->handlerRegistered = true;
            }

            if (!$this->shutdownRegistered) {
                Horde_Shutdown::add($this);
                $this->shutdownRegistered = true;
            }

            $this->setupApplied = true;
        }

        if ($start && !$this->active) {
            $this->start();
            $this->initialiseTimestamps();
        }
    }

    /**
     * Open the actual PHP session.
     *
     * Calls {@see session_start()}, marks the lifecycle active, and rebuilds
     * the modern {@see HordeSession} instance from the now-populated
     * `$_SESSION` payload.
     */
    public function start(): void
    {
        if ($this->active) {
            // Idempotency: session_start() emits a notice if called
            // twice. The legacy shim and the modern setup() can both
            // drive start(); guard so the second call is a coherent
            // no-op rather than a runtime warning.
            return;
        }

        // Limit session ID to 32 bytes. Session IDs are NOT cryptographically
        // secure hashes; they are just a way to generate random strings.
        ini_set('session.hash_function', '0');
        ini_set('session.hash_bits_per_character', '5');

        session_start();
        $this->active = true;

        // Rebuild HordeSession over the now-populated $_SESSION so encryptor
        // closures from HordeSessionFactory are preserved on the fresh
        // instance.
        $this->rebuildHordeSession();
    }

    /**
     * Mirror the modern session payload to `$_SESSION` and call
     * `session_write_close()`. Used by SESSION_READONLY mode.
     */
    public function close(): void
    {
        $this->active = false;
        $this->mirrorToSession();
        session_write_close();
    }

    /**
     * Login-fixation guard.
     *
     * Regenerates the session ID, clears all session data, rebuilds the
     * modern session over the now-empty `$_SESSION`, writes fresh
     * `_b`/`_r` timestamps, and rotates the {@see SessionSecret} key.
     * Idempotent: returns false on repeat calls within the same request.
     *
     * @return bool True if cleaned, false if already cleaned this request.
     */
    public function clean(): bool
    {
        if ($this->cleaned) {
            return false;
        }

        // login.php and Auth_Application::transparent can call clean() before
        // setup() has opened the session. session_regenerate_id() then fails
        // with "Session ID cannot be regenerated when there is no active
        // session" and the cleanup is incomplete. Open the session first.
        // Mirrors the equivalent guard on the legacy Horde_Session shim.
        if (!$this->active) {
            $this->start();
        }

        session_regenerate_id(true);
        session_unset();
        $_SESSION = [];
        $this->rebuildHordeSession();
        $this->initialiseTimestamps();

        $this->secret?->setKey();

        $this->cleaned = true;

        // Synchronous executor: any pending lifecycle intent has just been
        // resolved synchronously; clear so downstream processFlags() is a
        // coherent no-op.
        $this->getSession()->clearLifecycleFlags();

        return true;
    }

    /**
     * Hard logout. Destroys the PHP session, clears `$_SESSION`, rebuilds
     * the modern session over the empty payload, and clears the
     * {@see SessionSecret} key.
     */
    public function destroy(): void
    {
        if (isset($_SESSION)) {
            session_destroy();
        }
        $_SESSION = [];
        $this->rebuildHordeSession();
        $this->cleaned = true;
        $this->active = false;

        $this->secret?->clearKey();

        // Synchronous executor: clear pending intent so a downstream
        // processFlags() call does not attempt to re-destroy.
        $this->getSession()->clearLifecycleFlags();
    }

    /**
     * Periodic session ID rotation. Updates the regenerate-at deadline.
     *
     * Used by paths that detect {@see regenerationDue()} and want to
     * proactively rotate without going through clean()/destroy().
     */
    public function regenerate(): void
    {
        $this->mirrorToSession();
        session_regenerate_id(true);
        // The deadline is canonical on HordeSession at the top level.
        // The shim's addFinal() shutdown task mirrors HordeSession to
        // $_SESSION for legacy code that reads the superglobal directly.
        $this->getSession()->setRegenerationDeadline(
            $this->config->nextRegenerationDeadline(),
        );

        // Synchronous executor: clear pending intent so a downstream
        // processFlags() call sees a coherent no-op.
        $this->getSession()->clearLifecycleFlags();
    }

    /**
     * Act on the lifecycle intent markers carried by {@see HordeSession}.
     *
     * Reads {@see HordeSession::shouldRegenerate()} and
     * {@see HordeSession::isDestroyed()} and dispatches to the matching
     * synchronous executor. The executor clears the markers via
     * {@see HordeSession::clearLifecycleFlags()} as part of acting.
     *
     * Resolution policy: destruction wins over regeneration. A session
     * being destroyed need not bother rotating its id.
     *
     * Idempotent: calling repeatedly with no markers set is a no-op.
     *
     * Canonical reader of the markers. Other code paths in this class
     * and elsewhere SHOULD NOT read the markers directly. Setters can
     * live anywhere; readers go through here so the dispatch policy is
     * defined in one place.
     *
     * Always called explicitly by a caller that knows the right moment
     * (e.g. a controller after authentication, a future PSR-15
     * middleware, a logout handler). NEVER auto-fired from
     * {@see shutdown()} or any other implicit trigger. Lifecycle
     * dispatch is visible at the call site, not magic last-minute
     * behaviour.
     */
    public function processFlags(HordeSession $session): void
    {
        if ($session->isDestroyed()) {
            $this->destroy();
            return;
        }
        if ($session->shouldRegenerate()) {
            $this->regenerate();
        }
    }

    /**
     * Whether the current session is past its regenerate-at deadline.
     *
     * Replaces the legacy `$session->regenerate_due` magic property.
     *
     * Reads via the modern {@see HordeSession}, not via `$_SESSION`:
     * SessionLifecycle is the orchestrator over HordeSession + the PHP
     * session module, not a parallel reader of the same data. Time-based
     * (deadline) check, separate from {@see HordeSession::shouldRegenerate()}
     * which is intent-based.
     */
    public function regenerationDue(): bool
    {
        $regen = $this->getSession()->getRegenerationDeadline();
        return $regen !== null && time() >= $regen;
    }

    /**
     * Whether PHP's session is currently open for read/write.
     *
     * Convenience over {@see session_status()}. Modern callers can use
     * `session_status() === PHP_SESSION_ACTIVE` directly; this exists for
     * symmetry with the legacy `$session->isActive()` API.
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Horde_Shutdown_Task: mirror HordeSession's payload into `$_SESSION` so
     * PHP's native save handler picks up scoped/encrypted writes when it
     * runs at request shutdown.
     *
     * close() does the same thing but also explicitly calls
     * session_write_close(). For requests that never call close(), this
     * shutdown hook carries the modern data across to the persisted
     * session.
     */
    public function shutdown(): void
    {
        if ($this->active) {
            $this->mirrorToSession();
        }
    }

        /**
     * Resolve the current {@see HordeSession} from the injector.
     *
     * Lazily looked up on every call because {@see clean()} and
     * {@see destroy()} replace the injector singleton with a fresh
     * instance.
     */
    private function getSession(): HordeSession
    {
        return $this->injector->getInstance(HordeSession::class);
    }

    /**
     * Rebuild the modern session from the current `$_SESSION`.
     *
     * Used after session_start() (when `$_SESSION` is now populated from
     * the persisted backend), after clean() / destroy() (when `$_SESSION`
     * is cleared), and any other moment where the previous HordeSession
     * instance no longer reflects the data the rest of the request will
     * see.
     *
     * Sets the freshly-built instance as the injector singleton so callers
     * resolving HordeSession via getInstance see the same object the
     * lifecycle synchronises with.
     */
    private function rebuildHordeSession(): void
    {
        $fresh = $this->injector->createInstance(HordeSession::class);
        $this->injector->setInstance(HordeSession::class, $fresh);
    }

    /**
     * Write the begin and regenerate-at timestamps on first session start.
     *
     * No-op when the begin timestamp is already present, so re-running
     * setup() or restoring a persisted session does not reset it. Reads
     * via {@see HordeSession::getSessionBegin()} so the legacy
     * scoped-with-empty-subkey shape (`$data['_b']['']`) is treated as
     * absent and gets overwritten on the next setup; over time, all
     * sessions converge on the canonical top-level int shape.
     */
    private function initialiseTimestamps(): void
    {
        $session = $this->getSession();

        if ($session->getSessionBegin() !== null) {
            return;
        }

        $now = time();
        $session->setSessionBegin($now);
        $session->setRegenerationDeadline(
            $this->config->nextRegenerationDeadline($now),
        );
    }

    /**
     * Copy the modern session payload into `$_SESSION` so PHP's save
     * handler persists the up-to-date data. Used by close() and the
     * shutdown task.
     */
    private function mirrorToSession(): void
    {
        $_SESSION = $this->getSession()->toPayload();
    }
}
