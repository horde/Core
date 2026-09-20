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

use Horde\Core\Middleware\OAuthConsentMiddleware;
use Horde\Core\Session\SessionAccess;
use Horde\OAuth\Server\Handler\AuthorizationEndpoint;
use Horde\OAuth\Server\Repository\ConsentRepository;
use Horde\Injector\Injector;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class OAuthConsentMiddlewareFactory
{
    public function create(Injector $injector): OAuthConsentMiddleware
    {
        return new OAuthConsentMiddleware(
            $injector->get(AuthorizationEndpoint::class),
            $injector->get(ConsentRepository::class),
            $injector->get(SessionAccess::class),
            $injector->get(Horde_PageOutput::class),
            $injector->get(Horde_Notification_Handler::class),
            $injector->get(ResponseFactoryInterface::class),
            $injector->get(StreamFactoryInterface::class),
        );
    }
}
