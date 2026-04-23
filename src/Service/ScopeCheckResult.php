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

namespace Horde\Core\Service;

/**
 * Three-state result of a scope sufficiency check.
 */
enum ScopeCheckResult
{
    /** Token exists and covers all wanted scopes. */
    case Sufficient;

    /** Token exists but lacks one or more wanted scopes. */
    case Insufficient;

    /** No token stored for this user+provider pair. */
    case NoToken;
}
