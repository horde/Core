<?php
/**
 * Credential types supported by unified manager.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

enum CredentialType
{
    case OAuth;
    case Password;
}
