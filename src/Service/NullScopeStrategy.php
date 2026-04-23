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
 * Strategy for handling tokens where the provider did not return a scope field.
 *
 * Some providers (e.g. GitHub) omit the scope field from token responses.
 * This enum lets callers control how null scopes are interpreted during
 * scope sufficiency checks.
 */
enum NullScopeStrategy
{
    /** Treat null scope as covering all requested scopes (GitHub behavior). */
    case TreatAsSufficient;

    /** Treat null scope as covering no scopes (safe default). */
    case TreatAsInsufficient;
}
