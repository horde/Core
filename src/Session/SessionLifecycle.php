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
     * @param SessionEncryptionCoordinator|null $coordinator
     *                                        Optional. Mediates the drain /
     *                                        rotate-key / refill ceremony
     *                                        used by {@see regenerate()}
     *                                        and {@see clean()}. When null
     *                                        (legacy / test contexts), the
     *                                        lifecycle falls back to the
     *                                        inline reEncryptAll +
     *                                        secret->setKey path.
     * @param SessionAccessor|null  $accessor Optional. Request-scoped slot
     *                                        holding the currently-canonical
     *                                        HordeSession value. When null
     *                                        (legacy / test contexts), the
     *                                        lifecycle resolves it through
     *                                        the injector lazily on first
     *                                        use. Consumer services inject
     *                                        {@see SessionAccess} to reach
     *                                        this same slot. See
     *                                        horde/Core#190.
     */
    public function __construct(
        private readonly Injector $injector,
        private readonly SessionHandler $handler,
        private readonly SessionConfig $config,
        private readonly ?SessionSecret $secret = null,
        private readonly ?SessionEncryptionCoordinator $coordinator = null,
        private ?SessionAccessor $accessor = null,
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
        // $_SESSION may be initialised (Horde_Session constructor sets it to
        // []) without an active PHP session. session_status() is the
        // authoritative guard; $this->active mirrors it for the normal path.
        if ($this->active || session_status() === PHP_SESSION_ACTIVE) {
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
     *
     * Value-object semantics: the id-rotated session is a distinct
     * HordeSession value (SessionId is immutable). regenerate() drains
     * encrypted plaintext from the pre-rotation session, rotates the
     * PHP session id, mints a successor HordeSession with the new id
     * and old plaintext data carried forward, points the encryption
     * layer at the successor, and refills the encrypted slots on the
     * successor under the new key. The freshly-minted value is then
     * published via {@see publishSession()} so consumers reading
     * through {@see SessionAccess} pick it up on their next call.
     */
    public function regenerate(): void
    {
        $old = $this->getSession();

        if ($this->coordinator !== null) {
            // Modern path: drain under the OLD key (the encryption
            // closures captured by HordeSession resolve getKey()
            // against the current salt + session_id), rotate the
            // session id, then mint a successor value and refill
            // encrypted slots on it under the new derivation.
            $plain = $this->coordinator->drain($old);
            session_regenerate_id(true);
            $new = $this->mintSuccessor($old);
            $this->coordinator->rekey($new);
            $this->coordinator->refillInto($new, $plain);
            $this->publishSession($new);
        } else {
            // Fallback for test / legacy contexts without a wired
            // coordinator. reEncryptAll mutates the same object in
            // place — matches the pre-value-object behavior. Publish
            // the (mutated-in-place) instance so callers holding a
            // stale reference via the accessor pick up its state.
            $old->reEncryptAll(function (): void {
                session_regenerate_id(true);
                $this->secret?->setKey();
            });
            $this->publishSession($old);
            $new = $old;
        }

        // The deadline is canonical on HordeSession at the top level.
        // The shim's addFinal() shutdown task mirrors HordeSession to
        // $_SESSION for legacy code that reads the superglobal directly.
        $new->setRegenerationDeadline(
            $this->config->nextRegenerationDeadline(),
        );

        // Mirror AFTER re-encryption so the save handler picks up the
        // freshly re-encrypted payload, not the old ciphertext.
        $this->mirrorToSession();

        // Synchronous executor: clear pending intent so a downstream
        // processFlags() call sees a coherent no-op.
        $new->clearLifecycleFlags();
    }

    /**
     * Build the successor {@see HordeSession} for a regenerate().
     *
     * Composes:
     * - A fresh {@see SessionId} read from PHP's `session_id()` after
     *   `session_regenerate_id(true)` has rotated it.
     * - The old session's toPayload() — carries scoped values,
     *   timestamps, encrypted-slot map, and the ciphertexts of the
     *   encrypted slots (which are stale under the new key but will
     *   be overwritten by {@see SessionEncryptionCoordinator::refillInto()}).
     *
     * Uses the same encryptor/decryptor closures as the predecessor.
     * Both are captured on HordeSessionFactory at container-build time
     * and reference the same `$secret` service by reference; that means
     * a {@see SessionEncryptionCoordinator::rekey()} call afterwards
     * takes effect on the successor's next encrypt/decrypt invocation
     * without needing to hand new closures around.
     *
     * Delegates to {@see HordeSessionFactory::restore()} because that
     * path constructs a HordeSession from an explicit payload rather
     * than reading `$_SESSION`, which is stale relative to the drained
     * payload we want to pass in.
     */
    private function mintSuccessor(HordeSession $old): HordeSession
    {
        $factory = $this->injector->getInstance(HordeSessionFactory::class);
        $successor = $factory->restore(
            new SessionId((string) (session_id() ?: 'none')),
            $old->toPayload(),
        );
        // HordeSessionFactory::restore() returns Session (parent type).
        // In this codepath the factory is always the Horde-app
        // HordeSessionFactory whose restore() actually returns a
        // HordeSession, but assert the type for the type checker.
        if (!$successor instanceof HordeSession) {
            throw new \RuntimeException(
                'HordeSessionFactory::restore() returned '
                . get_debug_type($successor)
                . '; expected HordeSession.',
            );
        }
        return $successor;
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
     *
     * Honours {@see HordeSession::isDestroyed()}: when a controller
     * has marked the session destroyed but no synchronous executor
     * (clean / destroy / clearAuth) has acted on the marker yet, the
     * mirror is skipped. This catches the usage error where a legacy
     * controller calls markDestroyed() without going through
     * processFlags or a synchronous executor. Re-mirroring the
     * about-to-be-destroyed payload would leave the row in a worse
     * state than skipping. The marker stays set and the operator's
     * next request through the modern middleware actually destroys
     * the row.
     */
    public function shutdown(): void
    {
        if (!$this->active) {
            return;
        }
        if ($this->getSession()->isDestroyed()) {
            return;
        }
        $this->mirrorToSession();
    }

    /**
     * Resolve the {@see SessionAccessor} for this lifecycle.
     *
     * If a {@see SessionAccessor} was passed to the constructor, use it;
     * otherwise resolve one through the injector on first use. The
     * accessor is the request-scoped slot the lifecycle publishes fresh
     * HordeSession values to on start/clean/destroy/regenerate;
     * consumers reading through {@see SessionAccess} see whichever value
     * lives in that slot at call time.
     *
     * Lazily resolved so tests and bootstrap-time contexts that build
     * a SessionLifecycle without wiring the accessor still work — the
     * legacy-shape 5-argument constructor call in
     * {@see SessionLifecycleFactory::create()} continues to work
     * unchanged.
     */
    private function accessor(): SessionAccessor
    {
        if ($this->accessor === null) {
            $resolved = null;
            try {
                $resolved = $this->injector->getInstance(SessionAccess::class);
            } catch (\Throwable) {
                // No SessionAccess binding — legacy test contexts and
                // partial DI setups build a SessionLifecycle without
                // wiring the accessor. Fall through to the private
                // instance below; consumers going through SessionAccess
                // in those contexts get an independent accessor and
                // won't stay in sync with the lifecycle, which is fine
                // for the narrow test cases where nobody is reading
                // SessionAccess in the first place.
            }
            if (!$resolved instanceof SessionAccessor) {
                // Interface binding may resolve to a different
                // implementation, or resolution failed entirely.
                // Publish-based lifecycle management is only possible
                // against the concrete SessionAccessor. Fall back to
                // constructing one; it will not be shared with anyone
                // else. In test contexts that don't wire SessionAccess
                // this is exactly right; in production this signals a
                // broken bootstrap but the fallback keeps the request
                // running.
                $resolved = new SessionAccessor();
            }
            $this->accessor = $resolved;
        }
        return $this->accessor;
    }

    /**
     * Resolve the current {@see HordeSession} through the accessor.
     *
     * Retained as a private helper for the sake of readability inside
     * this class; a small enough number of internal call sites that
     * inlining would just add clutter.
     *
     * If the accessor holds no session yet (before start() or after
     * destroy()), falls back to resolving through the injector. This
     * matches the pre-accessor behavior for the narrow set of call
     * paths that read the session outside of an established lifecycle
     * — the shutdown task's isDestroyed() check being the notable one.
     */
    private function getSession(): HordeSession
    {
        if ($this->accessor()->hasCurrent()) {
            return $this->accessor()->current();
        }
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
     * Publishes the freshly-built instance to two places:
     * 1. The {@see SessionAccessor}, so consumers reading through
     *    {@see SessionAccess} pick up the new value on their next call
     *    (see horde/Core#190).
     * 2. The `HordeSession::class` injector binding — retained in
     *    parallel because dozens of transient callers still do
     *    `$injector->getInstance(HordeSession::class)->…` inline and
     *    expect the current value there too.
     *
     * The instance is also published on `$GLOBALS['injector']` when that
     * is a different scope. `SessionLifecycle` is resolved through a
     * `#[Factory]` binder, which passes a fresh child injector into the
     * factory (see {@see \Horde\Injector\Binder\Factory::create}), so
     * `$this->injector` is typically a child of the top-level container.
     * A `setInstance` on the child is invisible to consumers that resolve
     * HordeSession via `$GLOBALS['injector']` — notably the
     * `Horde_Session` shim's `_resolveModern()`. Publishing to both
     * scopes keeps them in agreement after a rotation. See
     * horde/Core#182.
     */
    private function rebuildHordeSession(): void
    {
        $fresh = $this->injector->createInstance(HordeSession::class);
        $this->publishSession($fresh);
    }

    /**
     * Publish `$fresh` as the current session across every place that
     * cares: the accessor (canonical for SessionAccess consumers), the
     * local injector's HordeSession binding, and — if we're inside a
     * child injector — the global injector's binding too.
     *
     * The three publications are kept together so start(), clean(),
     * destroy(), and regenerate() cannot drift on which places they
     * update.
     */
    private function publishSession(HordeSession $fresh): void
    {
        $this->accessor()->replaceWith($fresh);
        $this->injector->setInstance(HordeSession::class, $fresh);
        if (isset($GLOBALS['injector'])
            && $GLOBALS['injector'] !== $this->injector) {
            $GLOBALS['injector']->setInstance(HordeSession::class, $fresh);
        }
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
