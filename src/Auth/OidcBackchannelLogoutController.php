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
 * @author   Jean Charles Delépine <jean.charles.delepine@u-picardie.fr>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Auth;

use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\OAuthTokenService;
use Horde\Jwt\Exception\InvalidTokenException;
use Horde\Jwt\Key\Jwk;
use Horde\Jwt\Key\PublicKey;
use Horde\Jwt\TokenDecoder;
use Horde\Jwt\Verifier\Es256Verifier;
use Horde\Jwt\Verifier\Rs256Verifier;
use Horde\Jwt\Verifier\VerifierInterface;
use Horde_Cache;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * OidcBackchannelLogoutController
 *
 * Receives a signed logout_token JWT from the identity provider
 * (RFC 9470 / OpenID Connect Back-Channel Logout 1.0), validates it,
 * identifies the affected user and removes their tokens via OAuthTokenService.
 *
 * Route: POST /auth/oidc/backchannel-logout (HordeAuthType: NONE)
 *
 * Provider configuration (Apereo CAS example):
 *   logoutType : BACK_CHANNEL
 *   logoutUrl  : https://webmail.example.org/horde/auth/oidc/backchannel-logout
 *
 * The endpoint always returns HTTP 200 to prevent the provider from
 * retrying failed deliveries. Errors are logged internally.
 *
 * JWT validation performed:
 *   - Signature verified via horde/Jwt (RS256 or ES256)
 *   - iss verified against configured provider URL
 *   - aud verified against our client_id
 *   - exp verified (via TokenDecoder, with CLOCK_SKEW leeway)
 *   - iat must be recent (within CLOCK_SKEW seconds)
 *   - jti must not have been seen before (replay protection, 1h cache)
 *   - events must contain the back-channel logout event URI
 *   - nonce must be absent (spec requirement)
 *
 * Supported algorithms: RS256, ES256.
 */
class OidcBackchannelLogoutController implements RequestHandlerInterface
{
    private const CLOCK_SKEW    = 300;
    private const JTI_TTL       = 3600;
    private const BCL_EVENT_URI = 'http://schemas.openid.net/event/backchannel-logout';

    public function __construct(
        private readonly OAuthProviderConfigRepository $providerConfig,
        private readonly OAuthTokenService $tokenService,
        private readonly Horde_Cache $cache,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Spec: always return 200, even on error
        $ok = $this->responseFactory->createResponse(200);

        if ($request->getMethod() !== 'POST') {
            $this->logger->warning('[OidcBCL] Non-POST request rejected');
            return $ok;
        }

        $body        = $request->getParsedBody() ?? [];
        $logoutToken = is_array($body) ? ($body['logout_token'] ?? null) : null;

        if (empty($logoutToken)) {
            $this->logger->warning('[OidcBCL] Missing logout_token in POST body');
            return $ok;
        }

        $this->logger->info('[OidcBCL] Back-channel logout request received');

        // ── Peek at header/payload to resolve provider before verification ────

        $parts = explode('.', $logoutToken);
        if (count($parts) !== 3) {
            $this->logger->error('[OidcBCL] logout_token is not a valid JWT');
            return $ok;
        }

        $header  = json_decode($this->base64url($parts[0]), true);
        $payload = json_decode($this->base64url($parts[1]), true);

        if (!is_array($header) || !is_array($payload)) {
            $this->logger->error('[OidcBCL] logout_token: invalid JSON in header or payload');
            return $ok;
        }

        // ── Resolve provider by iss ───────────────────────────────────────────

        $issuer = rtrim((string) ($payload['iss'] ?? ''), '/');
        if ($issuer === '') {
            $this->logger->error('[OidcBCL] iss claim missing');
            return $ok;
        }

        $row = $this->findProviderByIssuer($issuer);
        if ($row === null) {
            $this->logger->error("[OidcBCL] No provider configured for issuer: $issuer");
            return $ok;
        }

        // ── Build verifier from provider JWKS ─────────────────────────────────

        $kid      = $header['kid'] ?? null;
        $alg      = strtoupper($header['alg'] ?? 'RS256');
        $verifier = $this->buildVerifier($row, $kid, $alg);

        if ($verifier === null) {
            $this->logger->error("[OidcBCL] Could not build verifier for alg=$alg");
            return $ok;
        }

        // ── Full JWT verification via horde/Jwt ───────────────────────────────

        $decoder = new TokenDecoder();
        try {
            $verified = $decoder->decode($logoutToken, $verifier, [
                'leeway'     => self::CLOCK_SKEW,
                'verify_iss' => rtrim($row['issuer'] ?? $row['url'] ?? '', '/'),
                'verify_aud' => $row['client_id'] ?? '',
            ]);
        } catch (InvalidTokenException $e) {
            $this->logger->error('[OidcBCL] JWT verification failed: ' . $e->getMessage());
            return $ok;
        }

        $this->logger->info('[OidcBCL] logout_token signature verified');

        // ── Additional BCL-specific claim validation ───────────────────────────

        $iat = $verified->getClaim('iat');
        if (!is_int($iat) || abs(time() - $iat) > self::CLOCK_SKEW) {
            $this->logger->error('[OidcBCL] iat missing or outside clock skew window');
            return $ok;
        }

        $jti = $verified->getClaim('jti');
        if (empty($jti)) {
            $this->logger->error('[OidcBCL] jti claim missing');
            return $ok;
        }

        $jtiKey = 'oidc_bcl_jti_' . hash('sha256', (string) $jti);
        if ($this->cache->get($jtiKey, self::JTI_TTL) !== false) {
            $this->logger->warning("[OidcBCL] Replayed jti rejected: $jti");
            return $ok;
        }
        $this->cache->set($jtiKey, '1', self::JTI_TTL);

        $events = $verified->getClaim('events');
        if (!is_array($events) || !array_key_exists(self::BCL_EVENT_URI, $events)) {
            $this->logger->error('[OidcBCL] events claim missing or invalid');
            return $ok;
        }

        if ($verified->hasClaim('nonce')) {
            $this->logger->error('[OidcBCL] logout_token must not contain a nonce claim');
            return $ok;
        }

        // ── Identify user and clear tokens ────────────────────────────────────

        $usernameClaim = $row['backchannel_username_claim'] ?? 'sub';
        $uid = (string) ($verified->getClaim($usernameClaim) ?? $verified->getClaim('sub') ?? '');

        if ($uid === '') {
            $this->logger->notice('[OidcBCL] Cannot identify user — no action taken');
            return $ok;
        }

        $this->logger->info("[OidcBCL] Back-channel logout for user: $uid (provider: {$row['provider_id']})");

        try {
            $this->tokenService->remove($uid, $row['provider_id']);
            $this->logger->info("[OidcBCL] Tokens removed for user: $uid");
        } catch (\Throwable $e) {
            $this->logger->error('[OidcBCL] Failed to remove tokens: ' . $e->getMessage());
        }

        return $ok;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function base64url(string $data): string
    {
        return (string) base64_decode(
            strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4)
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findProviderByIssuer(string $issuer): ?array
    {
        foreach ($this->providerConfig->listEnabled() as $row) {
            $rowIssuer = rtrim($row['issuer'] ?? $row['url'] ?? '', '/');
            if ($rowIssuer === $issuer) {
                return $row;
            }
        }
        return null;
    }

    private function buildVerifier(array $row, ?string $kid, string $alg): ?VerifierInterface
    {
        $publicKey = $this->resolvePublicKey($row, $kid);
        if ($publicKey === null) {
            return null;
        }

        return match ($alg) {
            'RS256' => new Rs256Verifier($publicKey),
            'ES256' => new Es256Verifier($publicKey),
            default => null,
        };
    }

    private function resolvePublicKey(array $row, ?string $kid): ?PublicKey
    {
        $cacheKey = 'oidc_jwks_' . ($row['provider_id'] ?? 'default');
        $jwks     = null;

        $cached = $this->cache->get($cacheKey, 3600);
        if ($cached !== false) {
            $jwks = json_decode($cached, true);
        }

        if (!is_array($jwks)) {
            $jwksUri = $row['jwks_uri'] ?? null;
            if ($jwksUri === null) {
                $base = rtrim($row['issuer'] ?? $row['url'] ?? '', '/');
                $raw  = @file_get_contents($base . '/.well-known/openid-configuration');
                $jwksUri = $raw !== false
                    ? (json_decode($raw, true)['jwks_uri'] ?? null)
                    : null;
            }
            if ($jwksUri === null) {
                return null;
            }
            $raw = @file_get_contents($jwksUri);
            if ($raw === false) {
                return null;
            }
            $jwks = json_decode($raw, true);
            $this->cache->set($cacheKey, $raw, 3600);
            $this->logger->debug('[OidcBCL] JWKS fetched from provider');
        }

        foreach ($jwks['keys'] ?? [] as $key) {
            if ($kid !== null && ($key['kid'] ?? null) !== $kid) {
                continue;
            }
            if (($key['use'] ?? 'sig') !== 'sig') {
                continue;
            }
            try {
                return Jwk::toPublicKey($key);
            } catch (\InvalidArgumentException $e) {
                continue;
            }
        }

        return null;
    }
}
