<?php

/**
 * Actions that can result from provisioning attempt.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

enum ProvisioningAction
{
    case Redirect;      // OAuth flow
    case ShowForm;      // Password entry form
    case UseSession;    // Already provisioned in session
    case Unavailable;   // Cannot provision
}
