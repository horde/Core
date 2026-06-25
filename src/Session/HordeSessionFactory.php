<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */

namespace Horde\Core\Session;

use Closure;
use Horde\Injector\Injector;
use Horde\SessionHandler\DefaultSessionFactory;
use Horde\SessionHandler\Session;
use Horde\SessionHandler\SessionId;

/**
 * Factory that creates HordeSession instances with encryption closures.
 *
 * In the Horde application framework, this factory is constructed by a
 * #[Factory] attribute with closures wrapping Horde_Secret_Cbc.
 */
class HordeSessionFactory extends DefaultSessionFactory
{
    /**
     * @param Closure|null $encryptor fn(string $plaintext): string
     * @param Closure|null $decryptor fn(string $ciphertext): string
     */
    public function __construct(
        private readonly ?Closure $encryptor = null,
        private readonly ?Closure $decryptor = null,
    ) {}

    public function createNew(SessionId $id): Session
    {
        $session = new HordeSession($id, [], $this->encryptor, $this->decryptor);
        $session->setSessionBegin(time());

        return $session;
    }

    /** @param array<string, mixed> $payload */
    public function restore(SessionId $id, array $payload): Session
    {
        return new HordeSession($id, $payload, $this->encryptor, $this->decryptor);
    }

    /**
     * DI factory method: wrap the current PHP session in a HordeSession.
     *
     * Called by the injector when HordeSession is requested via
     * its #[Factory] attribute. Resolves the configured Horde_Secret_Cbc
     * service from the injector (the binding registered in
     * DefaultInjectorBindings carries the IV from $conf['secret_key'])
     * and wraps it in lazy closures so the per-session key is read at
     * write/read time. setKey() runs during Horde_Session::clean() at
     * login, after this factory has already built the session, so
     * capturing the key eagerly would be wrong. The legacy binding name
     * `Horde_Secret_Cbc` is used deliberately. Asking for the
     * `Horde_Core_Secret_Cbc` class directly bypasses the factory and
     * yields an instance with no IV configured.
     */
    public function create(Injector $injector): HordeSession
    {
        $secret = $injector->getInstance('Horde_Secret_Cbc');
        $encryptor = static fn(string $plaintext): string
            => (string) $secret->write($secret->getKey(), $plaintext);
        $decryptor = static fn(string $ciphertext): string
            => (string) $secret->read($secret->getKey(), $ciphertext);

        $session = new HordeSession(
            new SessionId(session_id() ?: 'none'),
            $_SESSION ?? [],
            $encryptor,
            $decryptor,
        );

        // Wire the modern session into the secret service so subsequent
        // getKey() / setKey() calls read and write the per-session key
        // in the session payload instead of depending exclusively on
        // the legacy horde_secret_key cookie. This is what makes the
        // encrypted slots survive cookie eviction, RC5→RC7 upgrades,
        // and session-id rotation done without re-encrypt (imp #66).
        if (method_exists($secret, 'setSession')) {
            $secret->setSession($session);
        }

        return $session;
    }
}
