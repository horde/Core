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
 * Why an app's credentials transitioned from
 * {@see HasCredentialsState::Present} to
 * {@see HasCredentialsState::Invalidated}.
 *
 * Modern, type-safe equivalent of the disjointed
 * `Horde_Auth::REASON_*` class constants. Used by
 * {@see AuthCredentialStore::markInvalidated()} and read by
 * authentication-aware UI to present an appropriate prompt:
 *
 * - {@see BackendRejected}: the backend (IMAP, LDAP, …) rejected the
 *   stored credential at connection / bind time. Most common case;
 *   user typed the wrong password, or it changed elsewhere.
 * - {@see PasswordChanged}: the user changed their password through
 *   `passwd/` or an equivalent flow. All apps holding credentials get
 *   marked invalidated together.
 * - {@see IdleWipe}: a scheduled hardening loop wiped credentials
 *   after an idle threshold. Optional behaviour configured per
 *   deployment.
 * - {@see AdminForced}: an administrator action invalidated the
 *   credentials.
 * - {@see Unknown}: catch-all for cases that don't fit the above.
 *   Discouraged; prefer the specific reasons.
 */
enum InvalidationReason: string
{
    case BackendRejected = 'backend-rejected';
    case PasswordChanged = 'password-changed';
    case IdleWipe = 'idle-wipe';
    case AdminForced = 'admin-forced';
    case Unknown = 'unknown';
}
