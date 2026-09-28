<?php
/**
 * Factory for CredentialStore.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service\Factory;

use Horde\Core\Service\CredentialStore;
use Horde\Core\Service\NullCredentialStore;
use Horde_Injector;

class CredentialStoreFactory
{
    public function create(Horde_Injector $injector): CredentialStore
    {
        return new NullCredentialStore();
    }
}
