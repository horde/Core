<?php
/**
 * Factory for ServiceCredentialManager.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service\Factory;

use Horde\Core\Service\NullServiceCredentialManager;
use Horde\Core\Service\ServiceCredentialManager;
use Horde_Injector;

class ServiceCredentialManagerFactory
{
    public function create(Horde_Injector $injector): ServiceCredentialManager
    {
        return new NullServiceCredentialManager();
    }
}
