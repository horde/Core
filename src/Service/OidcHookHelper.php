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

use Horde\OAuth\Client\ProviderConfig;
use Horde\OAuth\Client\TokenRefresher;
use Horde\OAuth\Client\TokenSet;
use Horde_Injector;

/**
 * OidcHookHelper
 *
 * Static helpers for OIDC/XOAUTH2 hooks (imap_preauthenticate, dynamic_prefs,
 * transport_auth, smtp_credentials). Centralises token refresh logic so it is
 * not duplicated across hooks.php files.
 *
 * Usage from any hooks.php:
 *
 *   $tokenService   = $injector->getInstance(\Horde\Core\Service\OAuthTokenService::class);
 *   $providerConfig = $injector->getInstance(\Horde\Core\Service\OAuthProviderConfigRepository::class);
 *
 *   $row = \Horde\Core\Service\OidcHookHelper::findProviderForUser(
 *       $username, $tokenService, $providerConfig
 *   );
 *   if ($row === null) { // not an OAuth2 session
 *       ...
 *   }
 *
 *   $accessToken = \Horde\Core\Service\OidcHookHelper::getValidAccessToken(
 *       $username, $row, $tokenService, $injector
 *   );
 *
 *   $xoauth2User = \Horde\Core\Service\OidcHookHelper::xoauth2Username(
 *       $username, $row
 *   );
 */
class OidcHookHelper
{
    /**
     * Find the first enabled provider that has stored tokens for $username.
     *
     * Returns null if this is not an OAuth2 session.
     *
     * @param string                         $username
     * @param OAuthTokenService              $tokenService
     * @param OAuthProviderConfigRepository  $providerConfig
     * @return array<string,mixed>|null
     */
    public static function findProviderForUser(
        string $username,
        OAuthTokenService $tokenService,
        OAuthProviderConfigRepository $providerConfig,
    ): ?array {
        foreach ($providerConfig->listEnabled() as $row) {
            if ($tokenService->hasTokens($username, $row['provider_id'])) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Return a valid (non-expired) access token for $username, refreshing
     * transparently if necessary.
     *
     * Returns null if no tokens are stored, the token cannot be refreshed,
     * or the provider configuration is incomplete.
     *
     * @param string              $username
     * @param array<string,mixed> $row       Provider config row
     * @param OAuthTokenService   $tokenService
     * @param Horde_Injector      $injector
     */
    public static function getValidAccessToken(
        string $username,
        array $row,
        OAuthTokenService $tokenService,
        Horde_Injector $injector,
    ): ?string {
        try {
            $tokenSet = $tokenService->getTokenSet($username, $row['provider_id']);
        } catch (\Throwable $e) {
            return null;
        }

        if (!$tokenSet->isExpired(30)) {
            return $tokenSet->accessToken;
        }

        if ($tokenSet->refreshToken === null) {
            return null;
        }

        try {
            $config   = ProviderConfig::fromArray($row);
            $endpoint = $config->tokenEndpoint;
            if ($endpoint === null) {
                return null;
            }

            $refresher = new TokenRefresher(
                httpClient: $injector->getInstance('Psr\Http\Client\ClientInterface'),
                requestFactory: $injector->getInstance('Psr\Http\Message\RequestFactoryInterface'),
                streamFactory: $injector->getInstance('Psr\Http\Message\StreamFactoryInterface'),
                tokenEndpoint: $endpoint,
                clientId: $row['client_id'] ?? '',
                clientSecret: $row['client_secret'] ?? null,
            );

            $newSet = $refresher->refresh($tokenSet->refreshToken, $tokenSet->scope);

            // Preserve refresh token if provider did not issue a new one
            if ($newSet->refreshToken === null) {
                $newSet = new TokenSet(
                    accessToken: $newSet->accessToken,
                    tokenType: $newSet->tokenType,
                    expiresIn: $newSet->expiresIn,
                    refreshToken: $tokenSet->refreshToken,
                    scope: $newSet->scope ?? $tokenSet->scope,
                    idToken: $newSet->idToken ?? $tokenSet->idToken,
                    receivedAt: $newSet->receivedAt,
                );
            }

            $tokenService->store($username, $row['provider_id'], $newSet);
            return $newSet->accessToken;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve the username to use in XOAUTH2 strings.
     *
     * If the provider config has 'xoauth2_use_email' => true and a
     * 'xoauth2_domain' value, the domain is appended to the username
     * (e.g. "jdoe" → "jdoe@example.org").
     *
     * @param string              $username
     * @param array<string,mixed> $row  Provider config row
     */
    public static function xoauth2Username(string $username, array $row): string
    {
        $useEmail = (bool) ($row['xoauth2_use_email'] ?? false);
        $domain   = (string) ($row['xoauth2_domain'] ?? '');

        if (!$useEmail || $domain === '' || str_contains($username, '@')) {
            return $username;
        }

        return $username . '@' . $domain;
    }
}
