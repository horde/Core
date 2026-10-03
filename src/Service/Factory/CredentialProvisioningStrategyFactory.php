<?php

/**
 * Factory for CredentialProvisioningStrategy.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service\Factory;

use Horde\Core\Service\CredentialProvisioningStrategy;
use Horde\Core\Service\NullCredentialProvisioningStrategy;
use Horde\Injector\Injector;
use Horde_Injector;

class CredentialProvisioningStrategyFactory
{
    public function create(Horde_Injector|Injector $injector): CredentialProvisioningStrategy
    {
        return new NullCredentialProvisioningStrategy();
    }
}
