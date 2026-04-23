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

namespace Horde\Core\Service\Exception;

use Horde\OAuth\Client\TokenSet;
use RuntimeException;

/**
 * Thrown when a stored token lacks one or more required scopes.
 *
 * Carries the existing TokenSet and the missing scopes so callers
 * can initiate incremental consent.
 */
class OAuthInsufficientScopeException extends RuntimeException
{
    /**
     * @param string[] $missingScopes
     */
    public function __construct(
        private readonly TokenSet $tokenSet,
        private readonly array $missingScopes,
        string $message = '',
        int $code = 0,
    ) {
        if ($message === '') {
            $message = 'Token lacks required scopes: ' . implode(', ', $missingScopes);
        }

        parent::__construct($message, $code);
    }

    public function getTokenSet(): TokenSet
    {
        return $this->tokenSet;
    }

    /**
     * @return string[]
     */
    public function getMissingScopes(): array
    {
        return $this->missingScopes;
    }
}
