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

namespace Horde\Core\PageOutput;

use Horde\Core\Assets\JsDiscoverer;
use Horde\Core\Service\PrefsService;
use Horde\Core\Session\SessionAccess;
use Horde_Injector;
use Horde\Injector\Injector;
use Horde\Token\Token;
use Horde_Registry;

/**
 * Factory for ViewModeConfigurator.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ViewModeConfiguratorFactory
{
    public function create(Horde_Injector|Injector $injector): ViewModeConfigurator
    {
        return new ViewModeConfigurator(
            $injector->getInstance(Horde_Registry::class),
            $injector->getInstance(PrefsService::class),
            $injector->getInstance(SessionAccess::class),
            $injector->getInstance(JsDiscoverer::class),
            $injector->getInstance(Token::class),
        );
    }
}
