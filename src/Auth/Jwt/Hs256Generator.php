<?php

declare(strict_types=1);

namespace Horde\Core\Auth\Jwt;

use RuntimeException;

/**
 * JWT Generator using HS256 algorithm
 *
 * HS256 (HMAC with SHA-256) uses symmetric cryptography.
 * Suitable for scenarios where a shared secret can be securely distributed
 * to both the token issuer and verifier (e.g., session tokens).
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
class Hs256Generator
{
    /**
     * Generate a JWT using HS256 algorithm
     *
     * @param array $claims JWT claims (iss, sub, aud, exp, iat, etc.)
     * @param string $secret Shared secret for signing (minimum 256 bits recommended)
     * @param int $expirySeconds JWT expiry time in seconds (default: 3600 = 1 hour)
     * @return GeneratedJwt
     * @throws RuntimeException If JWT generation fails
     */
    public function generate(
        array $claims,
        string $secret,
        int $expirySeconds = 3600
    ): GeneratedJwt {
        if ($expirySeconds <= 0) {
            throw new RuntimeException('Expiry seconds must be positive');
        }

        if (trim($secret) === '') {
            throw new RuntimeException('Secret cannot be empty');
        }

        $now = time();
        $expiresAt = $now + $expirySeconds;

        // Build JWT header
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        // Merge provided claims with standard timestamp claims
        $payload = array_merge($claims, [
            'iat' => $claims['iat'] ?? $now,
            'exp' => $claims['exp'] ?? $expiresAt,
        ]);

        // Use provided exp if it exists
        $finalExpiresAt = $payload['exp'];

        // Encode header and payload
        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        // Create signature base
        $signatureBase = "{$headerEncoded}.{$payloadEncoded}";

        // Sign with HMAC SHA-256
        $signature = $this->sign($signatureBase, $secret);

        // Construct JWT
        $jwt = "{$signatureBase}.{$signature}";

        return new GeneratedJwt($jwt, $finalExpiresAt, $payload);
    }

    /**
     * Base64 URL-safe encoding
     *
     * @param string $data Data to encode
     * @return string Base64 URL-encoded string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Sign data with HS256 algorithm
     *
     * @param string $data Data to sign
     * @param string $secret Shared secret for signing
     * @return string Base64 URL-encoded signature
     */
    private function sign(string $data, string $secret): string
    {
        $signature = hash_hmac('sha256', $data, $secret, true);
        return $this->base64UrlEncode($signature);
    }
}
