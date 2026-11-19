<?php

declare(strict_types=1);

namespace Horde\Core\Factory;

use Horde\Core\Service\NullServiceAuthorizationRepository;
use Horde\Core\Service\ServiceAuthorizationRepository;
use Horde\Injector\Injector;

/** Factory for ServiceAuthorizationRepository - defaults to NullServiceAuthorizationRepository. */
class ServiceAuthorizationRepositoryFactory
{
    public function create(Injector $injector): ServiceAuthorizationRepository
    {
        return new NullServiceAuthorizationRepository();
    }
}
