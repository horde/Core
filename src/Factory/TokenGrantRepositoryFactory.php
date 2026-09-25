<?php

declare(strict_types=1);

namespace Horde\Core\Factory;

use Horde\Core\Service\NullTokenGrantRepository;
use Horde\Core\Service\TokenGrantRepository;
use Horde\Injector\Injector;

/** Factory for TokenGrantRepository - defaults to NullTokenGrantRepository. */
class TokenGrantRepositoryFactory
{
    public function create(Injector $injector): TokenGrantRepository
    {
        return new NullTokenGrantRepository();
    }
}
