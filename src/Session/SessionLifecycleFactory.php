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
use Horde\SessionHandler\SessionHandler;
use Horde_Secret_Cbc;

/**
 * DI factory for {@see SessionLifecycle}.
 *
 * Wires the modern {@see SessionHandler}, the {@see Injector} (used for
 * lazy {@see HordeSession} resolution), the loaded `horde` config
 * {@see State}, and the optional {@see Horde_Secret_Cbc} into a single
 * per-request lifecycle orchestrator.
 *
 * The legacy binding name `Horde_Secret_Cbc` is used deliberately. Asking
 * for the `Horde_Core_Secret_Cbc` class directly bypasses the binding and
 * yields an instance with no IV configured. Matches what
 * {@see HordeSessionFactory::create()} does.
 */
class SessionLifecycleFactory
{
    /**
     * Build the request-scoped {@see SessionLifecycle}.
     *
     * Resolves the `horde` config {@see State} through {@see ConfigLoader}
     * rather than reading `$GLOBALS['conf']` directly. Tests that need a
     * different config shape can construct {@see SessionLifecycle}
     * directly with their own {@see State}.
     */
    public function create(Injector $injector): SessionLifecycle
    {
        $handler = $injector->getInstance(SessionHandler::class);
        $config = $this->loadConfig($injector);

        $secret = null;
        try {
            $secret = $injector->getInstance('Horde_Secret_Cbc');
        } catch (\Throwable) {
            // No Horde_Secret_Cbc binding configured. Tests and
            // bootstrap-time contexts may run without one. Lifecycle
            // gracefully no-ops the rekey/clearKey calls when null.
        }

        return new SessionLifecycle($injector, $handler, $config, $secret);
    }

    private function loadConfig(Injector $injector): State
    {
        $loader = $injector->getInstance(ConfigLoader::class);

        return $loader->load('horde');
    }
}
