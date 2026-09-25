<?php

declare(strict_types=1);

namespace Horde\Core\Factory;

use Horde\Core\Service\NullServiceAuthorizationService;
use Horde\Core\Service\ServiceAuthorizationService;
use Horde\Injector\Injector;

/** Factory for ServiceAuthorizationService - defaults to NullServiceAuthorizationService. */
class ServiceAuthorizationServiceFactory
{
    public function create(Injector $injector): ServiceAuthorizationService
    {
        return new NullServiceAuthorizationService();
    }
}
