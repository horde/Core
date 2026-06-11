<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Auth;

/**
 * Per-app credential availability state on a {@see HordeSession}.
 *
 * Distinguishes three meaningfully different reasons why a session may
 * not have credentials for a given app:
 *
 * - {@see Present}: the session holds cleartext credentials for this app.
 *   Apps that need to reach a backend (IMAP, LDAP, Samba) can do so.
 * - {@see NeverHad}: this session was resumed from a remember-me token
 *   or a similar low-trust channel that did not carry credentials. The
 *   user is identified, but per-app authentication has to be redone.
 * - {@see Invalidated}: the session previously held credentials, but
 *   they have been wiped (rejected by the backend, password changed,
 *   admin force, scheduled idle wipe). Indicates a state change worth
 *   surfacing to the user with diagnostic context (see
 *   {@see InvalidationReason}).
 *
 * Conflating "never had" with "had and lost them" loses diagnostic
 * information. UX, audit logs, and reauth flows differ between the two.
 */
enum HasCredentialsState: string
{
    case Present = 'present';
    case NeverHad = 'never-had';
    case Invalidated = 'invalidated';
}
