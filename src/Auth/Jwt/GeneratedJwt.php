<?php

declare(strict_types=1);

namespace Horde\Core\Auth\Jwt;

/**
 * Represents a generated JWT with metadata
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class GeneratedJwt
{
    /**
     * @param string $token The JWT token string
     * @param int $expiresAt Unix timestamp when the JWT expires
     */
    public function __construct(
        public readonly string $token,
        public readonly int $expiresAt
    ) {}

    /**
     * Check if the JWT is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return time() >= $this->expiresAt;
    }

    /**
     * Get seconds until expiration
     *
     * @return int Seconds remaining (negative if expired)
     */
    public function getSecondsUntilExpiration(): int
    {
        return $this->expiresAt - time();
    }
}
