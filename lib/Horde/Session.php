<?php

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 */

use Horde\Core\Factory\TokenServiceFactory;
use Horde\Core\Horde;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\HordeSessionFactory;
use Horde\SessionHandler\SessionHandler as ModernSessionHandler;
use Horde\SessionHandler\SessionId;
use Horde\Token\Exception\TokenException;
use Horde\Token\Token as ModernToken;

/**
 * Backwards-compatible session facade.
 *
 * Once the canonical session implementation, now a thin shim over the modern
 * PSR-4 {@see HordeSession} for legacy callers. New code should inject
 * HordeSession directly.
 *
 * Roles of the shim:
 * - Keep the legacy public API ($session->get/set/exists/remove/getToken/...)
 *   and the $GLOBALS['session'] global stable while individual call sites
 *   migrate to the modern stack.
 * - Translate legacy calls to {@see HordeSession::getScoped()} et al.
 * - Cut tokens over to the HMAC-based {@see ModernToken} service in one
 *   place so the legacy stable-randomid contract is replaced atomically.
 *
 * Wire format ownership:
 *   The modern {@see HordeSession} owns the legacy ENCRYPT/TYPE_ARRAY/TYPE_OBJECT
 *   serialization invariants. Strings get a {@see HordeSession::NOT_SERIALIZED}
 *   prefix; arrays and objects are {@see Horde_Pack}-packed; encrypted values
 *   are encrypt-of-pack. The shim no longer pre-packs or pre-prefixes; it just
 *   hands raw values to {@see HordeSession::setScoped()} /
 *   {@see HordeSession::setEncrypted()} which encode for storage.
 *
 * Treatment of $_SESSION:
 *   $_SESSION is a dumb mirror, not the source of truth. The modern
 *   HordeSession owns the data. The shim mirrors HordeSession's payload back
 *   into $_SESSION before any code path that might read $_SESSION (PHP shutdown,
 *   session_destroy()). Direct $_SESSION[...] = ... writes by callers are not
 *   supported and will be silently overwritten on the next sync.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Core
 *
 * @property-read integer $begin             The timestamp when this session
 *                                           began (0 if session is not active).
 * @property-read boolean $regenerate_due    True if session ID is due for
 *                                           regeneration (since 2.5.0).
 * @property-read integer $regenerate_interval  The regeneration interval
 *                                           (since 2.5.0).
 * @property-write array $session_data       Manually set session data (since
 *                                           2.5.0).
 */
class Horde_Session implements Horde_Shutdown_Task
{
    /* Class constants. */
    public const BEGIN = '_b';
    public const ENCRYPTED = '_e'; /* @since 2.7.0 */
    public const MODIFIED = '_m'; /* @deprecated */
    public const PRUNE = '_p';
    public const REGENERATE = '_r'; /* @since 2.5.0 */

    public const TYPE_ARRAY = 1;
    public const TYPE_OBJECT = 2;
    public const ENCRYPT = 4; /* @since 2.7.0 */

    /**
     * Marker prefix for raw string values. Re-exported from
     * {@see HordeSession::NOT_SERIALIZED} so legacy callers that reference
     * `Horde_Session::NOT_SERIALIZED` keep working unchanged.
     */
    public const NOT_SERIALIZED = HordeSession::NOT_SERIALIZED;

    public const NONCE_ID = 'session_nonce'; /* @since 2.11.0 */
    public const TOKEN_ID = 'session_token';

    /** @deprecated Use Horde_Core_Cache_SessionObjects instead. */
    public const DATA = '_d';

    /**
     * Maximum size of the pruneable data store.
     *
     * @var integer
     */
    public $maxStore = 20;

    /**
     * BC stub for the legacy session handler. Some out-of-tree code sets
     * `$session->sessionHandler->changed = true` to flag a write; this object
     * accepts that without effect (the modern SessionHandler always persists
     * dirty sessions on shutdown).
     *
     * @var object
     */
    public object $sessionHandler;

    /**
     * The modern session object holding the actual data.
     */
    private HordeSession $modern;

    /**
     * The token service used for getToken()/checkToken(). Resolved lazily to
     * avoid a circular dependency at construction time: the legacy
     * TokenServiceFactory::create() path reads $GLOBALS['session'], which is
     * this very object, not yet assigned to the global at constructor entry.
     */
    private ?ModernToken $tokenService = null;

    /**
     * Optional explicit token service injected at construction time. When set,
     * {@see _tokenService()} returns this instead of building one from the
     * modern session.
     */
    private ?ModernToken $explicitTokenService = null;

    /**
     * Indicates that the session is active (read/write).
     */
    protected bool $_active = false;

    /**
     * Indicate that a new session ID has been generated for this page load.
     */
    protected bool $_cleansession = false;

    /**
     * Pointer to the session data. Kept for BC: some legacy code reads
     * `$session->session_data` (settable via __set) or peeks at this property
     * directly. Now points at $_SESSION, which the shim keeps mirrored from
     * the modern session.
     *
     * @var array
     */
    protected $_data;

    /**
     * Indicates that session data is read-only.
     */
    protected bool $_readonly = false;

    /**
     * On re-login, indicate whether we were previously authenticated.
     */
    protected ?bool $_relogin = null;

    /**
     * Constructor.
     *
     * All dependencies are optional: when omitted they are resolved from the
     * global injector. This keeps `new Horde_Session()` callers (legacy tests,
     * Horde_Test_Factory_Session, the SESSION_NONE Registry branch) working
     * unchanged while modern callers and DI can pass explicit instances.
     */
    public function __construct(
        ?HordeSession $modern = null,
        ?ModernToken $tokenService = null
    ) {
        $this->modern = $modern ?? $this->_resolveModern();
        $this->explicitTokenService = $tokenService;

        /* Make sure the global session variable is initialised and points to
         * the array the shim mirrors into. */
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $this->_data = &$_SESSION;

        /* The legacy handler property is exposed publicly; provide a stub so
         * out-of-tree code that sets `->changed = true` keeps working. */
        $this->sessionHandler = new class {
            public bool $changed = false;
        };
    }

    /**
     * Resolve the modern session at construction time. Prefers the global
     * injector (so encryptor/decryptor closures wired by HordeSessionFactory
     * are present); falls back to a minimal direct construction for legacy
     * test contexts that build a Horde_Session without setting up an
     * injector.
     */
    private function _resolveModern(): HordeSession
    {
        if (isset($GLOBALS['injector'])) {
            return $GLOBALS['injector']->getInstance(HordeSession::class);
        }

        $sid = (string) (session_id() ?: 'none');
        return new HordeSession(
            new SessionId($sid !== '' ? $sid : 'none'),
            $_SESSION ?? []
        );
    }

    /**
     */
    public function __get($name)
    {
        switch ($name) {
            case 'begin':
                if (!$this->_active && !$this->_relogin) {
                    return 0;
                }
                $value = $this->modern->getScoped(self::BEGIN, '');
                if ($value === null) {
                    $value = $this->_data[self::BEGIN] ?? 0;
                }

                return is_int($value) ? $value : 0;

            case 'regenerate_due':
                $regen = $this->_data[self::REGENERATE] ?? null;
                return is_int($regen) && time() >= $regen;

            case 'regenerate_interval':
                // DEFAULT: 6 hours
                return 21600;
        }

        return null;
    }

    /**
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'session_data':
                /* Used by the legacy SessionHandler factory's session-decode
                 * path (`readSessionData`) to swap in foreign session data
                 * temporarily. Replace both views. */
                $this->_data = &$value;
                $_SESSION = $value;
                $this->_rebuildModern();
                break;
        }
    }

    /**
     * Sets a custom session handler up, if there is one.
     *
     * Tunes PHP session ini settings, registers the modern PSR-4
     * {@see ModernSessionHandler} as PHP's save handler, and optionally starts
     * the session. Legacy callers in horde/base (login.php, LoginService) call
     * this after destroying a session on logout to start a fresh anonymous
     * session.
     *
     * @param boolean $start         Initiate the session?
     * @param string  $cache_limiter Override for the session cache limiter
     *                               value.
     * @param string  $session_id    The session ID to use.
     *
     * @throws Horde_Exception
     */
    public function setup(
        $start = true,
        $cache_limiter = null,
        $session_id = null
    ) {
        global $conf, $injector;

        ini_set('url_rewriter.tags', 0);

        if (!empty($conf['cookie']['domain'])
            && (strpos($conf['server']['name'], '.') === false)) {
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
                (string) ($conf['server']['name'] ?? ''),
                (string) $conf['cookie']['domain']
            ));
        }

        if (!empty($conf['session']['timeout'])) {
            ini_set('session.gc_maxlifetime', $conf['session']['timeout']);
        }

        session_set_cookie_params(
            (int) ($conf['session']['timeout'] ?? 0),
            (string) ($conf['cookie']['path'] ?? ''),
            (string) ($conf['cookie']['domain'] ?? ''),
            !empty($conf['use_ssl']) && $conf['use_ssl'] == 1,
            true
        );
        session_cache_limiter(
            is_null($cache_limiter)
                ? (string) ($conf['session']['cache_limiter'] ?? '')
                : $cache_limiter
        );
        session_name(urlencode((string) ($conf['session']['name'] ?? '')));
        if ($session_id) {
            session_id($session_id);
        }

        /* Install the modern handler. The legacy
         * Horde_Core_Factory_SessionHandler is no longer wire-bound; the modern
         * SessionHandlerFactory already covers every backend except mongo. */
        $modernHandler = $injector->getInstance(ModernSessionHandler::class);
        session_set_save_handler($modernHandler, true);

        /* Mirror the modern HordeSession payload back into $_SESSION before
         * PHP runs its own session save. The shim treats $_SESSION as a
         * write-only mirror at end-of-request: HordeSession owns the data,
         * and PHP's native save handler serialises whatever sits in $_SESSION
         * when it runs. Without a shutdown task here, only data written
         * directly to $_SESSION (e.g. _b/_r) survives across requests, and
         * scoped/encrypted writes that live in HordeSession are lost. */
        Horde_Shutdown::add($this);

        if ($start) {
            $this->start();
            $this->_start();
        }
    }

    /**
     * Starts the session.
     *
     * Calls session_start() and rebuilds the modern HordeSession from the now
     * populated $_SESSION so subsequent reads see the persisted data.
     */
    public function start()
    {
        /* Limit session ID to 32 bytes. Session IDs are NOT cryptographically
         * secure hashes. Instead, they are nothing more than a way to
         * generate random strings. */
        ini_set('session.hash_function', 0);
        ini_set('session.hash_bits_per_character', 5);

        session_start();
        $this->_active = true;
        $this->_data = &$_SESSION;
        $this->_rebuildModern();

        /* We have reopened a session. Check to make sure that authentication
         * status has not changed in the meantime. */
        if (!$this->_readonly
            && !is_null($this->_relogin)
            && (($GLOBALS['registry']->getAuth() !== false) !== $this->_relogin)) {
            Horde::log(
                'Previous session attempted to be reopened after authentication'
                . ' status change. All session modifications will be ignored.',
                Horde_Log::DEBUG
            );
            $this->_readonly = true;
        }
    }

    /**
     * Tasks to perform when starting a session.
     */
    private function _start()
    {
        $curr_time = time();

        /* Create internal data arrays. */
        if ($this->modern->getScoped(self::BEGIN, '') === null
            && !isset($this->_data[self::BEGIN])) {
            $this->_data[self::BEGIN] = $curr_time;
            $this->_data[self::REGENERATE] = $curr_time
                + $this->regenerate_interval;
            $this->modern->setScoped(self::BEGIN, '', $curr_time);
            $this->modern->setScoped(
                self::REGENERATE,
                '',
                $curr_time + $this->regenerate_interval
            );
        }
    }

    /**
     * Regenerate the session ID.
     *
     * @since 2.5.0
     */
    public function regenerate()
    {
        $this->_mirrorToSession();
        session_regenerate_id(true);
        $regenAt = time() + $this->regenerate_interval;
        $this->_data[self::REGENERATE] = $regenAt;
        $this->modern->setScoped(self::REGENERATE, '', $regenAt);

        /* Modern HordeSession handles re-encryption of values in its
         * encryption map under the new $_SESSION id-derived key, if the
         * factory wired encryptor/decryptor closures. */
    }

    /**
     * Destroys any existing session on login and make sure to use a new
     * session ID, to avoid session fixation issues. Should be called before
     * checking a login.
     *
     * @return boolean  True if the session was cleaned.
     */
    public function clean()
    {
        if ($this->_cleansession) {
            return false;
        }

        // login.php and Auth_Application::transparent can call clean() before
        // setup() has opened the session. session_regenerate_id() then fails
        // with "Session ID cannot be regenerated when there is no active
        // session" and the cleanup is incomplete. Open the session first.
        if (!$this->_active) {
            $this->start();
        }

        // Make sure to force a completely new session ID and clear all
        // session data.
        session_regenerate_id(true);
        session_unset();
        $_SESSION = [];
        $this->_data = &$_SESSION;
        $this->_rebuildModern();
        $this->_start();

        if (isset($GLOBALS['injector'])) {
            $GLOBALS['injector']->getInstance('Horde_Secret_Cbc')->setKey();
        }

        $this->_cleansession = true;

        return true;
    }

    /**
     * Close the current session.
     *
     * Mirrors the modern session payload back into $_SESSION so PHP's save
     * handler writes the up-to-date data, then calls session_write_close().
     */
    public function close()
    {
        $this->_active = false;
        $this->_relogin = isset($GLOBALS['registry'])
            && ($GLOBALS['registry']->getAuth() !== false);
        $this->_mirrorToSession();
        session_write_close();
    }

    /**
     * Shutdown task: mirror the modern session payload into $_SESSION so the
     * native PHP save handler picks up scoped/encrypted writes when it runs
     * automatically at request end.
     *
     * close() does the same thing but also explicitly calls
     * session_write_close(). For requests that never call close() (the
     * common write-mode path), this shutdown hook is what carries the
     * modern data across to the persisted session.
     */
    public function shutdown()
    {
        if ($this->_active) {
            $this->_mirrorToSession();
        }
    }

    /**
     * Destroy session data.
     */
    public function destroy()
    {
        if (isset($_SESSION)) {
            session_destroy();
        }
        $_SESSION = [];
        $this->_data = &$_SESSION;
        $this->_rebuildModern();
        $this->_cleansession = true;
        if (isset($GLOBALS['injector'])) {
            $GLOBALS['injector']->getInstance('Horde_Secret_Cbc')->clearKey();
        }
    }

    /**
     * Is the current session active (read/write)?
     *
     * @return boolean  True if the current session is active.
     */
    public function isActive()
    {
        return $this->_active;
    }

    /* Session variable access. */

    /**
     * Does the session variable exist?
     *
     * @param string $app   Application name.
     * @param string $name  Session variable name.
     *
     * @return boolean  True if session variable exists.
     */
    public function exists($app, $name)
    {
        return $this->modern->hasScoped($app, $name);
    }

    /**
     * Get the value of a session variable.
     *
     * Encoding/decoding (Horde_Pack-packed values, NOT_SERIALIZED-prefixed
     * strings) is owned by the modern {@see HordeSession}. The shim just
     * delegates and applies the legacy mask-based default-value semantics
     * (an absent value reads as [] under TYPE_ARRAY or stdClass under
     * TYPE_OBJECT) on top.
     *
     * @param string $app    Application name.
     * @param string $name   Session variable name.
     * @param integer $mask  One of:
     *   - Horde_Session::TYPE_ARRAY - Return an array value.
     *   - Horde_Session::TYPE_OBJECT - Return an object value.
     *
     * @return mixed  The value or null if the value doesn't exist.
     */
    public function get($app, $name, $mask = 0)
    {
        if ($this->modern->hasScoped($app, $name)) {
            if ($this->modern->isEncrypted($app, $name)) {
                return $this->modern->getEncrypted($app, $name);
            }
            return $this->modern->getScoped($app, $name);
        }

        if ($subkeys = $this->_subkeys($app, $name)) {
            $ret = [];
            foreach ($subkeys as $k => $v) {
                $ret[$k] = $this->get($app, $v, $mask);
            }
            return $ret;
        }

        /* @todo Deprecated. */
        if (is_string($name) && strpos($name, self::DATA) === 0) {
            return $this->retrieve($name);
        }

        switch ($mask) {
            case self::TYPE_ARRAY:
                return [];

            case self::TYPE_OBJECT:
                return new stdClass();
        }

        return null;
    }

    /**
     * Sets the value of a session variable.
     *
     * Encoding (Horde_Pack-packing for arrays/objects, NOT_SERIALIZED prefix
     * for strings, raw for other scalars) is owned by the modern
     * {@see HordeSession}. The shim just routes the value to the right
     * accessor: encrypted slots through {@see HordeSession::setEncrypted()},
     * everything else through {@see HordeSession::setScoped()}.
     *
     * The TYPE_ARRAY/TYPE_OBJECT masks are no-ops at runtime — modern
     * HordeSession infers the wire shape from the value type itself. The
     * constants are kept for source-compat with callers that pass them.
     *
     * @param string $app    Application name.
     * @param string $name   Session variable name.
     * @param mixed $value   Session variable value.
     * @param integer $mask  One of:
     *   - Horde_Session::TYPE_ARRAY: Force save as an array value (no-op).
     *   - Horde_Session::TYPE_OBJECT: Force save as an object value (no-op).
     *   - Horde_Session::ENCRYPT: Encrypt the value. (since 2.7.0)
     */
    public function set($app, $name, $value, $mask = 0)
    {
        if ($this->_readonly) {
            return;
        }

        if ($mask & self::ENCRYPT) {
            $this->modern->setEncrypted($app, $name, $value);
            $this->sessionHandler->changed = true;
            return;
        }

        $this->modern->setScoped($app, $name, $value);
        $this->sessionHandler->changed = true;
    }

    /**
     * Remove session key(s).
     *
     * @param string $app   Application name.
     * @param string $name  Session variable name.
     */
    public function remove($app, $name = null)
    {
        if ($this->_readonly) {
            return;
        }

        if (is_null($name)) {
            foreach ($this->modern->keysForApp($app) as $key) {
                $this->modern->removeScoped($app, $key);
            }
            $this->sessionHandler->changed = true;
        } elseif ($this->modern->hasScoped($app, $name)) {
            $this->modern->removeScoped($app, $name);
            $this->sessionHandler->changed = true;
        } else {
            foreach ($this->_subkeys($app, $name) as $val) {
                $this->remove($app, $val);
            }
        }
    }

    /**
     * Return the list of subkeys for a master key.
     *
     * @param string $app   Application name.
     * @param string $name  Session variable name.
     *
     * @return array  Subkeyname (keys) and session variable name (values).
     */
    private function _subkeys($app, $name)
    {
        $ret = [];

        if (!is_string($name) || $name === '') {
            return $ret;
        }
        if ($name[strlen($name) - 1] !== '/') {
            return $ret;
        }

        foreach ($this->modern->keysForApp($app) as $k) {
            if (strpos($k, $name) === 0) {
                $ret[substr($k, strlen($name))] = $k;
            }
        }

        return $ret;
    }

    /* Session tokens. */

    /**
     * Returns a session-bound CSRF token.
     *
     * Backed by {@see ModernToken}: an HMAC over the per-session secret and a
     * fixed seed. NOT stable across calls within a session — the legacy
     * "stable random id stored in $_SESSION['horde']['session_token']" contract
     * is replaced with a stateless HMAC token. All known framework + app call
     * sites round-trip this value (emit, POST, verify), so the change is safe.
     *
     * @return string  Session token.
     */
    public function getToken()
    {
        return (string) $this->_tokenService()->generate(HordeSession::CSRF_SEED);
    }

    /**
     * Checks the validity of the session token.
     *
     * @param string $token  Token to check.
     *
     * @throws Horde_Exception
     */
    public function checkToken($token)
    {
        try {
            $valid = $this->_tokenService()->isValid(
                (string) $token,
                HordeSession::CSRF_SEED
            );
        } catch (TokenException $e) {
            throw new Horde_Exception('Invalid token!');
        }
        if (!$valid) {
            throw new Horde_Exception('Invalid token!');
        }
    }

    /**
     * Resolve the token service lazily, avoiding the circular dependency
     * between Horde_Session::__construct() and the legacy
     * TokenServiceFactory::create() path which reads $GLOBALS['session'].
     */
    private function _tokenService(): ModernToken
    {
        if ($this->explicitTokenService !== null) {
            return $this->explicitTokenService;
        }
        if ($this->tokenService !== null) {
            return $this->tokenService;
        }

        if (isset($GLOBALS['injector'])) {
            $factory = $GLOBALS['injector']->getInstance(
                TokenServiceFactory::class
            );
            $this->tokenService = $factory->createForSession($this->modern);
            return $this->tokenService;
        }

        /* No injector available — use the deployment secret as a last
         * resort. This path is only hit in misbuilt test contexts. */
        $factory = new TokenServiceFactory($GLOBALS['injector'] ?? null);
        $this->tokenService = $factory->createFromDeploymentSecret();
        return $this->tokenService;
    }

    /* Session nonces. */

    /**
     * Returns a single-use, session nonce.
     *
     * @since 2.11.0
     *
     * @return string  Session nonce.
     */
    public function getNonce()
    {
        $id = strval(new Horde_Support_Randomid());

        $nonces = $this->_nonces();
        $nonces[] = $id;
        $this->modern->setScoped('horde', self::NONCE_ID, array_values($nonces));
        $this->sessionHandler->changed = true;

        return $id;
    }

    /**
     * Checks the validity of the session nonce.
     *
     * @since 2.11.0
     *
     * @param string $nonce  Nonce to check.
     *
     * @throws Horde_Exception
     */
    public function checkNonce($nonce)
    {
        $nonces = $this->_nonces();
        if (($pos = array_search($nonce, $nonces, true)) === false) {
            throw new Horde_Exception('Invalid token!');
        }
        unset($nonces[$pos]);
        $this->modern->setScoped('horde', self::NONCE_ID, array_values($nonces));
        $this->sessionHandler->changed = true;
    }

    /**
     * @return array<int, string>
     */
    private function _nonces(): array
    {
        $nonces = $this->modern->getScoped('horde', self::NONCE_ID);

        return is_array($nonces) ? array_values($nonces) : [];
    }

    /* Session object storage (deprecated facades). */

    /**
     * @deprecated  Use Horde_Core_Cache_SessionObjects instead.
     */
    public function store($data, $prune = true, $id = null)
    {
        global $injector;

        if (is_null($id)) {
            $id = strval(new Horde_Support_Randomid());
        }

        $ob = new Horde_Core_Cache_SessionObjects();
        $ob->set($id, $injector->getInstance('Horde_Pack')->pack($data));

        return $id;
    }

    /**
     * @deprecated  Use Horde_Core_Cache_SessionObjects instead.
     */
    public function retrieve($id)
    {
        global $injector;

        $ob = new Horde_Core_Cache_SessionObjects();
        try {
            return $injector->getInstance('Horde_Pack')->unpack($ob->get($id));
        } catch (Horde_Pack_Exception $e) {
        }

        return null;
    }

    /**
     * @deprecated  Use Horde_Core_Cache_SessionObjects instead.
     */
    public function purge($id)
    {
        $ob = new Horde_Core_Cache_SessionObjects();
        return $ob->expire($id);
    }

    /* Internal helpers — modern <-> $_SESSION mirror plumbing. */

    /**
     * Mirror the modern session payload back into $_SESSION so PHP's save
     * handler writes the up-to-date data on shutdown.
     */
    private function _mirrorToSession(): void
    {
        $_SESSION = $this->modern->toPayload();
        $this->_data = &$_SESSION;
    }

    /**
     * Rebuild the modern session from the current $_SESSION via the injector
     * so encryptor/decryptor closures wired by HordeSessionFactory are
     * preserved. Used by start() (after session_start populates $_SESSION),
     * clean() and destroy() (after $_SESSION is cleared), and by
     * __set('session_data') (after a foreign payload is swapped in).
     *
     * The freshly-built instance is also registered as the injector singleton
     * so callers that resolve HordeSession via getInstance (e.g. modern
     * AuthCredentialStore) see the same object the shim mirrors back to
     * $_SESSION on close(). Without this, two HordeSession instances coexist:
     * the shim's authoritative one and a stale singleton that silently swallows
     * writes from modern callers.
     */
    private function _rebuildModern(): void
    {
        if (isset($GLOBALS['injector'])) {
            $this->modern = $GLOBALS['injector']->createInstance(
                HordeSession::class
            );
            $GLOBALS['injector']->setInstance(
                HordeSession::class,
                $this->modern
            );
            return;
        }

        /* Fallback for bootstrapping/test contexts without an injector. No
         * encryption closures available; encrypted reads will return raw bytes
         * and encrypted writes will fail loudly via setEncrypted(). */
        $sid = (string) (session_id() ?: 'none');
        $this->modern = new HordeSession($sid !== '' ? new SessionId($sid) : new SessionId('none'), $_SESSION ?? []);
    }
}
