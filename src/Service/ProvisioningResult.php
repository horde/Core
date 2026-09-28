<?php
/**
 * Result of credential provisioning attempt.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

final class ProvisioningResult
{
    public function __construct(
        public readonly ProvisioningAction $action,
        public readonly ?string $redirectUri = null,
        public readonly ?array $formFields = null,
    ) {}
}
