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

use Horde\Core\Service\NullOAuthHttpClientService;
use Horde\Core\Service\OAuthHttpClientService;
use Horde\Injector\Injector;

/**
 * Default factory returning NullOAuthHttpClientService.
 *
 * horde/base overrides this binding with its own factory that returns
 * DefaultOAuthHttpClientService.
 */
class OAuthHttpClientServiceFactory
{
    public function create(Injector $injector): OAuthHttpClientService
    {
        return new NullOAuthHttpClientService();
    }
}
