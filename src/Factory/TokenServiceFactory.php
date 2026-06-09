<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\Core\Service\HordeDbService;
use Horde\Core\Session\HordeSession;
use Horde\Injector\Injector;
use Horde\Token\Storage\FileStorage;
use Horde\Token\Storage\NullStorage;
use Horde\Token\Storage\SqlStorage;
use Horde\Token\Storage\TokenStorageInterface;
use Horde\Token\Token;
use Horde\Token\TokenConfig;
use Horde\Token\TokenGenerator;
use Horde\Token\TokenValidator;
use Horde_Support_Randomid;
use RuntimeException;

/**
 * Factory for the modern Horde\Token\Token CSRF service.
 *
 * Two access patterns supported:
 *
 * 1. **Legacy DI binding** — `Horde\Token\Token::class` resolves through
 *    {@see create()}. The HMAC secret is sourced from the global
 *    `$session` for backward compatibility with Horde_Form V3 and other
 *    consumers wired before this factory grew explicit secret-source
 *    methods.
 *
 * 2. **Explicit secret source** — Inject `TokenServiceFactory` itself
 *    (the Injector autowires concrete classes) and call
 *    {@see createForSession()}, {@see createFromDeploymentSecret()},
 *    or the underlying primitive {@see createFromSecret()}. This is the
 *    preferred approach for new code: the call site declares which
 *    secret scope the resulting Token signs with, and no globals are
 *    consulted.
 *
 * Configuration is read via {@see ConfigLoader} (no `$GLOBALS['conf']`
 * access). The expensive dependencies (storage backend, lifetime,
 * timeout) are cached on the factory instance after the first lookup;
 * subsequent `createFrom*` calls reuse them and only allocate a fresh
 * {@see Token} (cheap — five property assignments).
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class TokenServiceFactory
{
    private ?TokenStorageInterface $storage = null;
    private ?int $tokenLifetime = null;
    private ?int $timeout = null;

    public function __construct(
        private readonly Injector $injector,
    ) {}

    /**
     * Legacy entry: produce a Token whose signing secret is sourced from
     * the global $session.
     *
     * Form V3 / whups consume this via the
     * `Horde\Token\Token::class => TokenServiceFactory::class` DI binding.
     * New code should prefer {@see createForSession()},
     * {@see createFromDeploymentSecret()}, or {@see createFromSecret()}.
     *
     * The `Injector` parameter is part of the legacy DI binding contract;
     * we ignore it since the factory already holds an injector reference.
     */
    public function create(Injector $injector): Token
    {
        $secret = $this->resolveSessionSecretFromGlobal();

        return $this->createFromSecret($secret);
    }

    /**
     * Build a Token using the per-session secret stored on $session.
     *
     * Reads the secret from `session['horde']['token_secret_key']`, or
     * generates one and stores it there if missing. The same key location
     * is used by the legacy {@see create()} path and by Horde_Form V3,
     * so tokens produced by either path are mutually verifiable inside
     * one session.
     */
    public function createForSession(HordeSession $session): Token
    {
        $secret = $this->resolveSessionSecret($session);

        return $this->createFromSecret($secret);
    }

    /**
     * Build a Token signed with the deployment-wide secret.
     *
     * Suitable for tokens that must validate across multiple sessions or
     * outside any session (admin actions, scheduled jobs, links the
     * server emits to itself). Reads the secret from
     * `$conf['secret_key']` — the random string unique to this Horde
     * installation, configured at install time.
     *
     * Note: this is the **server-side** deployment secret, not the legacy
     * `Horde_Secret::getKey()` value (which is per-user-cookie and
     * unsuitable for cross-session signing). The PSR-4
     * `Horde\Secret\SecretManager` deliberately does not expose its
     * internal key, so we read the configuration directly via ConfigLoader.
     *
     * @throws RuntimeException When `$conf['secret_key']` is missing or empty.
     */
    public function createFromDeploymentSecret(): Token
    {
        $secret = $this->resolveDeploymentSecret();

        return $this->createFromSecret($secret);
    }

    /**
     * Underlying primitive: build a Token signed with an explicit secret.
     *
     * Public so callers with their own secret-source policies can plug
     * in (per-identity tokens, externally-supplied keys, test fixtures).
     * The other `createFrom*` methods are syntactic sugar over this one.
     *
     * Storage and configuration are reused across all calls — the per-call
     * cost is just five property assignments on the new Token. Callers
     * that need to process many secrets in one request (iterating sessions,
     * composing tokens for multiple identities) should usually call
     * {@see Token::generate()} / {@see Token::isValid()} with the
     * per-call `$secret` parameter on a single Token instance instead.
     */
    public function createFromSecret(string $secret): Token
    {
        $storage = $this->getStorage();
        $config = new TokenConfig(
            secret: $secret,
            tokenLifetime: $this->getTokenLifetime(),
            timeout: $this->getTimeout(),
        );

        return new Token(
            new TokenGenerator($config),
            new TokenValidator($config, $storage),
            $storage,
            $config,
        );
    }

    /* ---------------------------------------------------------------- *
     *  Internal helpers                                                 *
     * ---------------------------------------------------------------- */

    private function loadState(): State
    {
        $loader = $this->injector->getInstance(ConfigLoader::class);

        return $loader->load('horde');
    }

    /**
     * @throws RuntimeException When the storage backend cannot be built.
     */
    private function getStorage(): TokenStorageInterface
    {
        if ($this->storage !== null) {
            return $this->storage;
        }

        $state = $this->loadState();
        $driver = strtolower((string) $state->get('token.driver', 'null'));

        if ($driver === 'none' || $driver === '') {
            $driver = 'null';
        }

        $params = $state->get('token.params', []);
        if (!is_array($params)) {
            $params = [];
        }

        $this->storage = match ($driver) {
            'sql' => $this->buildSqlStorage($params),
            'file' => $this->buildFileStorage($params),
            default => new NullStorage(),
        };

        return $this->storage;
    }

    private function getTokenLifetime(): int
    {
        if ($this->tokenLifetime !== null) {
            return $this->tokenLifetime;
        }

        $state = $this->loadState();
        $minutes = $state->get('urls.token_lifetime');

        $this->tokenLifetime = is_numeric($minutes) && (int) $minutes > 0
            ? (int) $minutes * 60
            : -1;

        return $this->tokenLifetime;
    }

    private function getTimeout(): int
    {
        if ($this->timeout !== null) {
            return $this->timeout;
        }

        $state = $this->loadState();
        $params = $state->get('token.params', []);
        if (!is_array($params)) {
            $params = [];
        }

        $this->timeout = isset($params['timeout']) && is_numeric($params['timeout'])
            ? (int) $params['timeout']
            : 86400;

        return $this->timeout;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildSqlStorage(array $params): SqlStorage
    {
        $dbService = $this->injector->getInstance(HordeDbService::class);
        $table = isset($params['table']) && is_string($params['table'])
            ? $params['table']
            : 'horde_tokens';

        return new SqlStorage($dbService->getAdapter(), $this->getTimeout(), $table);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function buildFileStorage(array $params): FileStorage
    {
        $dir = isset($params['token_dir']) && is_string($params['token_dir']) && $params['token_dir'] !== ''
            ? $params['token_dir']
            : sys_get_temp_dir();

        return new FileStorage($dir, $this->getTimeout());
    }

    /**
     * Source the per-session secret from the global $session, generating
     * and storing it on first use. Matches legacy
     * Horde_Core_Factory_Token behaviour for wire compatibility.
     */
    private function resolveSessionSecretFromGlobal(): string
    {
        global $session;

        if (!$session->exists('horde', 'token_secret_key')) {
            $session->set(
                'horde',
                'token_secret_key',
                strval(new Horde_Support_Randomid())
            );
        }

        return (string) $session->get('horde', 'token_secret_key');
    }

    /**
     * Source the per-session secret from a HordeSession instance,
     * generating and storing it on first use. Wire-compatible with the
     * global-$session path: both read/write the same scoped slot.
     */
    private function resolveSessionSecret(HordeSession $session): string
    {
        if (!$session->hasScoped('horde', 'token_secret_key')) {
            $session->setScoped(
                'horde',
                'token_secret_key',
                strval(new Horde_Support_Randomid())
            );
        }

        $value = $session->getScoped('horde', 'token_secret_key');

        return is_string($value) ? $value : '';
    }

    /**
     * @throws RuntimeException When `$conf['secret_key']` is missing.
     */
    private function resolveDeploymentSecret(): string
    {
        $state = $this->loadState();
        $secret = $state->get('secret_key');

        if (!is_string($secret) || $secret === '') {
            throw new RuntimeException(
                'TokenServiceFactory::createFromDeploymentSecret() requires '
                . '$conf[\'secret_key\'] to be set to a non-empty string. '
                . 'Generate one in the horde configuration UI or in conf.php.'
            );
        }

        return $secret;
    }
}
