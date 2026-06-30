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
use Horde\Injector\Injector;

/**
 * DI factory for {@see SessionSecret}.
 *
 * Bridges the class-based `SessionSecret` type-hint to the legacy
 * string-named `Horde_Secret_Cbc` binding. Constructor-autowiring
 * callers can therefore depend on the typed interface while the
 * underlying instance remains the single per-request
 * {@see \Horde_Core_Secret_Cbc} configured by
 * {@see \Horde_Core_Factory_Secret_Cbc}.
 *
 * Returns the same instance the legacy binding returns: there is one
 * secret service per request, regardless of which name resolves it.
 */
class SessionSecretFactory
{
    public function create(Injector $injector): SessionSecret
    {
        $resolved = $injector->getInstance('Horde_Secret_Cbc');

        if (!$resolved instanceof SessionSecret) {
            throw new \LogicException(
                'Horde_Secret_Cbc binding does not resolve to a '
                . 'SessionSecret implementation. DI configuration error.'
            );
        }

        return $resolved;
    }
}
