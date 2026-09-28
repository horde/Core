<?php
/**
 * Actions that can result from credential acquisition attempt.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

enum AcquisitionAction
{
    case Redirect;      // OAuth flow
    case ShowForm;      // Password entry
    case AlreadyExists; // No action needed
    case Unavailable;   // Cannot acquire
}
