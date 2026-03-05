<?php

declare(strict_types=1);

namespace Horde\Core\Auth\Jwt;

use RuntimeException;

/**
 * JWT Generator using RS256 algorithm
 *
 * RS256 (RSA Signature with SHA-256) uses asymmetric cryptography.
 * Suitable for scenarios where the public key can be distributed
 * for verification while the private key remains secret.
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
class Rs256Generator implements JwtGeneratorInterface
{
    /**
     * Generate a JWT using RS256 algorithm
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
    ): GeneratedJwt {
        if ($expirySeconds <= 0) {
            throw new RuntimeException('Expiry seconds must be positive');
        }

        $now = time();
        $expiresAt = $now + $expirySeconds;

        // Build JWT header
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        // Build JWT payload
        $payload = [
            'iss' => $appId,
            'iat' => $now,
            'exp' => $expiresAt,
        ];

        // Encode header and payload
        $headerEncoded = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        // Create signature base
        $signatureBase = "{$headerEncoded}.{$payloadEncoded}";

        // Sign with private key
        $signature = $this->sign($signatureBase, $privateKey);

        // Construct JWT
        $jwt = "{$signatureBase}.{$signature}";

        return new GeneratedJwt($jwt, $expiresAt);
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
     * Sign data with RS256 algorithm
     *
     * @param string $data Data to sign
     * @param PrivateKey $privateKey Private key for signing
     * @return string Base64 URL-encoded signature
     * @throws RuntimeException If signing fails
     */
    private function sign(string $data, PrivateKey $privateKey): string
    {
        $keyResource = $privateKey->getResource();

        $signature = '';
        $success = openssl_sign($data, $signature, $keyResource, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new RuntimeException('Failed to sign JWT: ' . openssl_error_string());
        }

        return $this->base64UrlEncode($signature);
    }
}
