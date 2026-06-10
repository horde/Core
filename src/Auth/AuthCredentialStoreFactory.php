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

namespace Horde\Core\Auth;

use Horde\Core\Session\HordeSession;
use Horde\Injector\Injector;

/**
 * DI factory for {@see AuthCredentialStore}.
 *
 * Resolves the modern {@see HordeSession} from the injector and hands it to
 * the store. Kept as a separate factory file (rather than an anonymous
 * `#[Factory]` closure) so legacy `Horde_Injector` lookups against the FQCN
 * resolve via the same code path as the modern `#[Factory]` attribute.
 */
class AuthCredentialStoreFactory
{
    public function create(Injector $injector): AuthCredentialStore
    {
        return new AuthCredentialStore(
            $injector->getInstance(HordeSession::class),
        );
    }
}
