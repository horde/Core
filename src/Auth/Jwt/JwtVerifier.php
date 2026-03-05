<?php

declare(strict_types=1);

namespace Horde\Core\Auth\Jwt;

use InvalidArgumentException;

/**
 * JWT Verifier for validating and decoding JWTs
 *
 * Supports HS256 (HMAC) and RS256 (RSA) algorithms.
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
class JwtVerifier
{
    /**
     * Verify and decode a JWT using HS256 algorithm
     *
     * @param string $token JWT token to verify
     * @param string $secret Shared secret for verification
     * @param array $options Verification options:
     *   - 'verify_exp' => bool (default: true) - Verify expiration
     *   - 'verify_nbf' => bool (default: true) - Verify not before
     *   - 'verify_iss' => string|null (default: null) - Expected issuer
     *   - 'verify_aud' => string|array|null (default: null) - Expected audience
     *   - 'leeway' => int (default: 0) - Leeway in seconds for time validation
     * @return VerifiedJwt
     * @throws InvalidArgumentException If token is invalid or verification fails
     */
    public function verifyHs256(string $token, string $secret, array $options = []): VerifiedJwt
    {
        // Set default options
        $options = array_merge([
            'verify_exp' => true,
            'verify_nbf' => true,
            'verify_iss' => null,
            'verify_aud' => null,
            'leeway' => 0,
        ], $options);

        if (trim($secret) === '') {
            throw new InvalidArgumentException('Secret cannot be empty');
        }

        // Parse and validate token structure
        [$header, $payload, $providedSignature] = $this->parseToken($token);

        // Verify algorithm
        if (($header['alg'] ?? '') !== 'HS256') {
            throw new InvalidArgumentException('Token algorithm is not HS256');
        }

        // Verify signature
        $parts = explode('.', $token);
        $signatureBase = "{$parts[0]}.{$parts[1]}";
        $expectedSignature = $this->signHs256($signatureBase, $secret);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidArgumentException('Invalid token signature');
        }

        // Verify claims
        $this->verifyClaims($payload, $options);

        return new VerifiedJwt($token, $payload);
    }

    /**
     * Verify and decode a JWT using RS256 algorithm
     *
     * @param string $token JWT token to verify
     * @param string $publicKeyPem Public key in PEM format
     * @param array $options Verification options (same as verifyHs256)
     * @return VerifiedJwt
     * @throws InvalidArgumentException If token is invalid or verification fails
     */
    public function verifyRs256(string $token, string $publicKeyPem, array $options = []): VerifiedJwt
    {
        // Set default options
        $options = array_merge([
            'verify_exp' => true,
            'verify_nbf' => true,
            'verify_iss' => null,
            'verify_aud' => null,
            'leeway' => 0,
        ], $options);

        if (trim($publicKeyPem) === '') {
            throw new InvalidArgumentException('Public key cannot be empty');
        }

        // Parse and validate token structure
        [$header, $payload, $providedSignature] = $this->parseToken($token);

        // Verify algorithm
        if (($header['alg'] ?? '') !== 'RS256') {
            throw new InvalidArgumentException('Token algorithm is not RS256');
        }

        // Load public key
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            throw new InvalidArgumentException('Invalid public key format: ' . openssl_error_string());
        }

        // Verify signature
        $parts = explode('.', $token);
        $signatureBase = "{$parts[0]}.{$parts[1]}";
        $signature = $this->base64UrlDecode($providedSignature);

        $result = openssl_verify($signatureBase, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($result !== 1) {
            throw new InvalidArgumentException('Invalid token signature');
        }

        // Verify claims
        $this->verifyClaims($payload, $options);

        return new VerifiedJwt($token, $payload);
    }

    /**
     * Parse JWT token into header, payload, and signature
     *
     * @param string $token JWT token
     * @return array [header, payload, signature]
     * @throws InvalidArgumentException If token structure is invalid
     */
    private function parseToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid JWT structure: must have three parts');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Decode header
        try {
            $headerJson = $this->base64UrlDecode($headerEncoded);
            $header = json_decode($headerJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid JWT header: ' . $e->getMessage());
        }

        // Decode payload
        try {
            $payloadJson = $this->base64UrlDecode($payloadEncoded);
            $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            throw new InvalidArgumentException('Invalid JWT payload: ' . $e->getMessage());
        }

        return [$header, $payload, $signatureEncoded];
    }

    /**
     * Verify JWT claims
     *
     * @param array $payload JWT payload
     * @param array $options Verification options
     * @throws InvalidArgumentException If claims are invalid
     */
    private function verifyClaims(array $payload, array $options): void
    {
        $now = time();
        $leeway = $options['leeway'];

        // Verify expiration (exp)
        if ($options['verify_exp'] && isset($payload['exp'])) {
            if ($now >= ($payload['exp'] + $leeway)) {
                throw new InvalidArgumentException('Token has expired');
            }
        }

        // Verify not before (nbf)
        if ($options['verify_nbf'] && isset($payload['nbf'])) {
            if ($now < ($payload['nbf'] - $leeway)) {
                throw new InvalidArgumentException('Token not yet valid');
            }
        }

        // Verify issuer (iss)
        if ($options['verify_iss'] !== null) {
            if (!isset($payload['iss']) || $payload['iss'] !== $options['verify_iss']) {
                throw new InvalidArgumentException('Invalid token issuer');
            }
        }

        // Verify audience (aud)
        if ($options['verify_aud'] !== null) {
            if (!isset($payload['aud'])) {
                throw new InvalidArgumentException('Token missing audience claim');
            }

            $expectedAud = is_array($options['verify_aud']) ? $options['verify_aud'] : [$options['verify_aud']];
            $actualAud = is_array($payload['aud']) ? $payload['aud'] : [$payload['aud']];

            // Check if any expected audience is in the actual audience list
            $match = false;
            foreach ($expectedAud as $expected) {
                if (in_array($expected, $actualAud, true)) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                throw new InvalidArgumentException('Invalid token audience');
            }
        }
    }

    /**
     * Sign data with HS256 algorithm
     *
     * @param string $data Data to sign
     * @param string $secret Shared secret
     * @return string Base64 URL-encoded signature
     */
    private function signHs256(string $data, string $secret): string
    {
        $signature = hash_hmac('sha256', $data, $secret, true);
        return $this->base64UrlEncode($signature);
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
     * Base64 URL-safe decoding
     *
     * @param string $data Data to decode
     * @return string Decoded data
     */
    private function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) % 4, '=', STR_PAD_RIGHT);
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
