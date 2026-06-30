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
use Horde\Injector\Injector;
use Horde\SessionHandler\SessionHandler;
use Throwable;

/**
 * DI factory for {@see SessionLifecycle}.
 *
 * Wires the modern {@see SessionHandler}, the {@see Injector} (used for
 * lazy {@see HordeSession} resolution), the typed
 * {@see SessionConfig} built by {@see SessionConfigFactory}, and the
 * optional {@see SessionSecret} cipher into a single per-request
 * lifecycle orchestrator.
 *
 * The legacy `Horde_Secret_Cbc` binding name is queried first because
 * existing installs configure the cipher under that string. The
 * resolved instance is checked against {@see SessionSecret} (which the
 * real `Horde_Core_Secret_Cbc` implements) before being passed to the
 * lifecycle: tests and minimal bootstraps may bind the legacy name to
 * a fixture that does not implement the contract, in which case the
 * lifecycle runs without re-keying.
 */
class SessionLifecycleFactory
{
    /**
     * Build the request-scoped {@see SessionLifecycle}.
     *
     * Resolves {@see SessionConfig} through the injector. Tests that
     * need a different config shape can construct {@see SessionLifecycle}
     * directly with their own {@see SessionConfig}.
     */
    public function create(Injector $injector): SessionLifecycle
    {
        $handler = $injector->getInstance(SessionHandler::class);
        $config = $injector->getInstance(SessionConfig::class);

        $secret = null;
        try {
            $resolved = $injector->getInstance('Horde_Secret_Cbc');
            if ($resolved instanceof SessionSecret) {
                $secret = $resolved;
            }
            // Resolved-but-not-SessionSecret: legacy fixture or test
            // double. Treat as if no cipher were available; lifecycle
            // no-ops the setKey/clearKey calls.
        } catch (Throwable) {
            // No Horde_Secret_Cbc binding configured. Tests and
            // bootstrap-time contexts may run without one. Lifecycle
            // gracefully no-ops the rekey/clearKey calls when null.
        }

        $coordinator = null;
        if ($secret !== null) {
            // Resolve the encryption coordinator through the injector
            // so middleware (which auto-wires the same type) and the
            // lifecycle share one instance. The lifecycle's regenerate()
            // routes the drain / rotate / refill ceremony through it;
            // clean() uses setKey directly because there's no payload
            // to drain.
            try {
                $coordinator = $injector->getInstance(SessionEncryptionCoordinator::class);
            } catch (Throwable) {
                // No coordinator binding configured. Lifecycle falls
                // back to its inline reEncryptAll path; behaviour is
                // equivalent at the encryption-ceremony level, just
                // less decoupled.
            }
        }

        return new SessionLifecycle($injector, $handler, $config, $secret, $coordinator);
    }
}
