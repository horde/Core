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

use Horde\Core\Config\BackendConfigLoader;
use Horde\Core\Registry\Nlsconfig;
use Horde\Core\Session\SessionAccess;
use Horde_Injector;
use Horde\Injector\Injector;
use Throwable;

/**
 * Factory for the {@see Nlsconfig} `LanguageContext` implementation.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class NlsconfigFactory
{
    public function create(Horde_Injector|Injector $injector): Nlsconfig
    {
        $prefs = null;
        try {
            $prefs = $injector->getInstance('Horde_Prefs');
        } catch (Throwable) {
            // Preferences unavailable (e.g. pre-auth request) — proceed
            // without; the cascade falls through to the next source.
        }

        return new Nlsconfig(
            $injector->getInstance(SessionAccess::class),
            $injector->getInstance(BackendConfigLoader::class),
            $prefs,
        );
    }
}
