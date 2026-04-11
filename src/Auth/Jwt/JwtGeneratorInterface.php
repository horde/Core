<?php

declare(strict_types=1);

namespace Horde\Core\Auth\Jwt;

use RuntimeException;

/**
 * Interface for JWT generation
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
interface JwtGeneratorInterface
{
    /**
     * Generate a JWT
     *
     * @param int $appId Application/issuer ID (iss claim)
     * @param PrivateKey $privateKey Private key for signing
     * @param int $expirySeconds JWT expiry time in seconds
     * @return GeneratedJwt
     * @throws RuntimeException If JWT generation fails
     */
    public function generate(
        int $appId,
        PrivateKey $privateKey,
        int $expirySeconds = 600
    ): GeneratedJwt;
}
