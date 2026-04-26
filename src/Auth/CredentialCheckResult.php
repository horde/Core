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
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Auth;

use Horde_Auth;

/**
 * Result of a credential check without login.
 *
 * Provides a typed alternative to a bare boolean for callers that
 * need to distinguish why authentication failed (locked account,
 * expired password, bad credentials).
 */
enum CredentialCheckResult
{
    /** Credentials are valid. */
    case Valid;

    /** Credentials are invalid (wrong password or unknown user). */
    case Invalid;

    /** Account is locked (too many failed attempts or admin lock). */
    case Locked;

    /** Credentials have expired (forced password change required). */
    case Expired;

    /**
     * Build a result from a Horde_Auth reason code.
     */
    public static function fromAuthReason(int $reason): self
    {
        return match ($reason) {
            Horde_Auth::REASON_LOCKED => self::Locked,
            Horde_Auth::REASON_EXPIRED => self::Expired,
            default => self::Invalid,
        };
    }
}
