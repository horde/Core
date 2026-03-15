<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
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

use Horde\Core\Service\IdentityService;
use Horde\Core\Service\PrefsService;
use Horde_Injector;

/**
 * Factory for IdentityService
 *
 * Creates identity management service with PrefsService dependency.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class IdentityServiceFactory
{
    /**
     * Create IdentityService instance
     *
     * @param Horde_Injector $injector Dependency injector
     * @return IdentityService Identity service instance
     */
    public function create(Horde_Injector $injector): IdentityService
    {
        $prefsService = $injector->getInstance(PrefsService::class);
        return new IdentityService($prefsService);
    }
}
