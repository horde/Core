<?php

declare(strict_types=1);

namespace Horde\Core\Auth\Jwt;

/**
 * Represents a verified JWT with decoded claims
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
class VerifiedJwt
{
    /**
     * @param string $token The original JWT token string
     * @param array $claims Decoded JWT claims
     */
    public function __construct(
        public readonly string $token,
        public readonly array $claims
    ) {}

    /**
     * Get a specific claim value
     *
     * @param string $name Claim name (e.g., 'sub', 'iss', 'aud')
     * @param mixed $default Default value if claim doesn't exist
     * @return mixed
     */
    public function getClaim(string $name, mixed $default = null): mixed
    {
        return $this->claims[$name] ?? $default;
    }

    /**
     * Check if a claim exists
     *
     * @param string $name Claim name
     * @return bool
     */
    public function hasClaim(string $name): bool
    {
        return array_key_exists($name, $this->claims);
    }

    /**
     * Get the subject (sub claim)
     *
     * @return string|null
     */
    public function getSubject(): ?string
    {
        return $this->getClaim('sub');
    }

    /**
     * Get the issuer (iss claim)
     *
     * @return string|null
     */
    public function getIssuer(): ?string
    {
        return $this->getClaim('iss');
    }

    /**
     * Get the audience (aud claim)
     *
     * @return array|string|null
     */
    public function getAudience(): array|string|null
    {
        return $this->getClaim('aud');
    }

    /**
     * Get the expiration timestamp (exp claim)
     *
     * @return int|null
     */
    public function getExpiration(): ?int
    {
        return $this->getClaim('exp');
    }

    /**
     * Get the issued at timestamp (iat claim)
     *
     * @return int|null
     */
    public function getIssuedAt(): ?int
    {
        return $this->getClaim('iat');
    }

    /**
     * Check if the JWT is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        $exp = $this->getExpiration();
        if ($exp === null) {
            return false;
        }
        return time() >= $exp;
    }
}
