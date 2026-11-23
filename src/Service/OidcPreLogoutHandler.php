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

namespace Horde\Core\Service;

use Horde\Core\Uri\RouteUrlWriter;
use Horde\OAuth\Client\OAuth2Client;
use Horde\OAuth\Client\ProviderConfig;
use Horde_Auth;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * OidcPreLogoutHandler
 *
 * Handles OIDC session cleanup before Horde destroys the authenticated
 * session. Called by LoginService::performLogout() via PreLogoutHandlerInterface.
 *
 * Since this handler is called while the session is still active, the
 * authenticated username is directly available.
 *
 * Actions performed:
 *   1. Removes tokens from OAuthTokenService
 *   2. Optionally revokes them at the provider (logout_type = revoke_and_slo)
 *   3. Returns the provider's end_session_endpoint as 'redirect' so that
 *      LoginService::performLogout() can redirect to it after clearAuth()
 *
 * Logout strategy is read from the provider config field 'logout_type':
 *   'local'          — clear tokens locally only
 *   'slo'            — clear tokens + prepare SLO redirect
 *   'revoke_and_slo' — revoke tokens at provider + prepare SLO redirect
 */
class OidcPreLogoutHandler implements PreLogoutHandlerInterface
{
    public function __construct(
        private readonly OAuthProviderConfigRepository $providerConfig,
        private readonly OAuthTokenService $tokenService,
        private readonly RouteUrlWriter $urlWriter,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function onBeforeLogout(string $userId, int $reason): array
    {
        // Only act on voluntary logouts — not session expiry or forced kicks
        if ($reason !== Horde_Auth::REASON_LOGOUT) {
            return [];
        }

        // Find the provider that has tokens for this user
        $row = null;
        foreach ($this->providerConfig->listEnabled() as $candidate) {
            if ($this->tokenService->hasTokens($userId, $candidate['provider_id'])) {
                $row = $candidate;
                break;
            }
        }

        if ($row === null) {
            return []; // Not an OAuth2 session
        }

        $logoutType = $row['logout_type'] ?? 'local';
        $this->logger->debug("[OidcPreLogout] Strategy '$logoutType' for user $userId");

        // ── 1. Revoke tokens at provider if requested ─────────────────────────

        if ($logoutType === 'revoke_and_slo') {
            $this->revokeTokens($userId, $row);
        }

        // ── 2. Remove tokens from local storage ──────────────────────────────

        try {
            $this->tokenService->remove($userId, $row['provider_id']);
            $this->logger->info("[OidcPreLogout] Tokens removed for user: $userId");
        } catch (Throwable $e) {
            $this->logger->warning('[OidcPreLogout] Could not remove tokens: ' . $e->getMessage());
        }

        if ($logoutType === 'slo' || $logoutType === 'revoke_and_slo') {
            $sloUrl = $this->resolveSloUrl($row);
            if ($sloUrl !== null) {
                $postLogout = $row['post_logout_redirect_uri']
                    ?? ((string) $this->urlWriter->absoluteUrlFor('HordeServicesPortal'));
                $sep    = str_contains($sloUrl, '?') ? '&' : '?';
                $target = $sloUrl . $sep
                    . 'post_logout_redirect_uri=' . urlencode($postLogout)
                    . '&service=' . urlencode($postLogout);

                return ['redirect' => $target];
            }
        }

        return [];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveSloUrl(array $row): ?string
    {
        if (!empty($row['end_session_endpoint'])) {
            return $row['end_session_endpoint'];
        }

        $base = rtrim($row['issuer'] ?? $row['url'] ?? '', '/');
        if ($base === '') {
            return null;
        }

        try {
            $raw = @file_get_contents($base . '/.well-known/openid-configuration');
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                $endpoint = $decoded['end_session_endpoint'] ?? null;
                if ($endpoint) {
                    return $endpoint;
                }
            }
        } catch (Throwable $e) {
            $this->logger->warning('[OidcPreLogout] Discovery failed: ' . $e->getMessage());
        }

        return null;
    }

    private function revokeTokens(string $userId, array $row): void
    {
        try {
            $tokenSet = $this->tokenService->getTokenSet($userId, $row['provider_id']);
            $config   = ProviderConfig::fromArray($row);

            if ($config->revocationEndpoint === null) {
                $this->logger->debug('[OidcPreLogout] No revocation endpoint, skipping');
                return;
            }

            $client = new OAuth2Client(
                provider: $config,
                clientId: $row['client_id'] ?? '',
                clientSecret: $row['client_secret'] ?? null,
                redirectUri: '',
                httpClient: $this->httpClient,
                requestFactory: $this->requestFactory,
                streamFactory: $this->streamFactory,
            );

            if ($tokenSet->refreshToken !== null) {
                $client->revokeToken($tokenSet->refreshToken, 'refresh_token');
            }
            $client->revokeToken($tokenSet->accessToken, 'access_token');
            $this->logger->info('[OidcPreLogout] Tokens revoked at provider');
        } catch (Throwable $e) {
            $this->logger->warning('[OidcPreLogout] Revocation failed (continuing): ' . $e->getMessage());
        }
    }
}
