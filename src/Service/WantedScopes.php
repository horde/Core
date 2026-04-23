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

use Horde\OAuth\Client\ScopeSet;

/**
 * Describes the scopes a caller requires from a stored token.
 *
 * Wraps a ScopeSet together with a strategy for handling tokens
 * where the provider did not return a scope field.
 */
final class WantedScopes
{
    public function __construct(
        public readonly ScopeSet $scopes,
        public readonly NullScopeStrategy $nullStrategy = NullScopeStrategy::TreatAsInsufficient,
    ) {}

    public static function of(string ...$scopes): self
    {
        return new self(new ScopeSet(...$scopes));
    }

    public function isEmpty(): bool
    {
        return $this->scopes->isEmpty();
    }
}
