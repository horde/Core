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

namespace Horde\Core\Factory;

use Horde\Core\Secret\SessionSecret;
use Horde\Core\Session\SessionEncryptionCoordinator;
use Horde\Injector\Injector;

/**
 * DI factory for {@see SessionEncryptionCoordinator}.
 *
 * Builds the per-request singleton with the resolved
 * {@see SessionSecret}. Bound in {@see \Horde\Core\DefaultInjectorBindings}
 * so constructor-autowiring middleware ({@see \Horde\Core\Middleware\HordeSessionMiddleware})
 * and explicit lifecycle wiring
 * ({@see \Horde\Core\Session\SessionLifecycleFactory}) both resolve the
 * same instance.
 */
class SessionEncryptionCoordinatorFactory
{
    public function create(Injector $injector): SessionEncryptionCoordinator
    {
        $secret = $injector->getInstance(SessionSecret::class);

        return new SessionEncryptionCoordinator($secret);
    }
}
