<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Jean Charles Delépine <jean.charles.delepine@u-picardie.fr>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Service;

/**
 * PreLogoutHandlerInterface
 *
 * Implement this interface to run code before Horde clears the authenticated
 * session during logout. Handlers are called by LoginService::performLogout()
 * after CSRF verification and audit logging but BEFORE clearAuth(), so the
 * authenticated username is still available.
 *
 * Typical use cases:
 *   - Revoke OAuth2/OIDC tokens at the provider (RP-Initiated Logout / SLO)
 *   - Invalidate active JWTs (blacklist)
 *   - Close ActiveSync or SAML sessions
 *   - Release document locks held by the user
 *   - Write an exact logout timestamp to an external audit log
 *
 * Handlers must not throw — any exception should be caught internally and
 * logged. A failing handler must never prevent the logout from completing.
 *
 * Register handlers via the DI container (see DefaultInjectorBindings).
 */
interface PreLogoutHandlerInterface
{
    /**
     * Called before the authenticated session is destroyed.
     *
     * @param string $userId  The authenticated Horde username
     * @param int    $reason  The logout reason (one of Horde_Auth::REASON_*)
     */
    public function onBeforeLogout(string $userId, int $reason): array;
}
