<?php

/**
 * Result of credential acquisition attempt.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

final class CredentialAcquisitionResult
{
    public function __construct(
        public readonly AcquisitionAction $action,
        public readonly ?string $redirectUri = null,
        public readonly ?array $formFields = null,
    ) {}
}
