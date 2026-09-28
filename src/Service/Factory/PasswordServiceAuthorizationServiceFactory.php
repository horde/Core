<?php
/**
 * Factory for PasswordServiceAuthorizationService.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service\Factory;

use Horde\Core\Service\NullPasswordServiceAuthorizationService;
use Horde\Core\Service\PasswordServiceAuthorizationService;
use Horde_Injector;

class PasswordServiceAuthorizationServiceFactory
{
    public function create(Horde_Injector $injector): PasswordServiceAuthorizationService
    {
        return new NullPasswordServiceAuthorizationService();
    }
}
