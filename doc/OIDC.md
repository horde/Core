# OIDC / OAuth2 Authentication

Horde supports authentication via an external OpenID Connect (OIDC)
identity provider such as Apereo CAS or Keycloak. Users are redirected
to the provider's login page; Horde never handles passwords directly.
Access and refresh tokens are stored server-side and used for XOAUTH2
authentication against IMAP and SMTP.

## Requirements

- A configured OIDC/OAuth2 identity provider with:
  - An application registered with a Client ID and Client Secret
  - The Horde callback URL whitelisted as a redirect URI
- SQL database access (required for token storage in production)
- PHP `openssl` extension (for JWT verification)

## Architecture

```
Browser → login.php → /auth/oauth/login/:providerId → IdP login page
       ← callback  ← /settings/oauth/callback        ← IdP redirect
```

After the callback, `OAuthAccountController` stores the token set and
calls `setAuth()`. Subsequent requests are validated by
`Horde_Core_Auth_Oidc::validateAuth()`, which checks that tokens are
still present in the repository. IMAP and SMTP connections use XOAUTH2
via hooks.

On logout, `OidcPreLogoutHandler` runs before `clearAuth()`, optionally
revoking tokens at the provider and redirecting to the IdP's
`end_session_endpoint` for Single Log-Out (SLO).

Back-channel logout (RFC 9470) is supported via a dedicated endpoint
that receives a signed `logout_token` JWT from the IdP and removes the
affected user's tokens, causing session invalidation on the next request.

## Installation

### 1. Database migration

Run the Horde database migration tool after updating the codebase:

```bash
/path/to/horde/vendor/bin/horde-db-migrate horde up
```

This creates the `horde_oauth_tokens` table for token storage and adds
the OIDC-specific columns to `horde_oauth_providers`.

### 2. Auth driver

In `var/config/horde/conf.php`:

```php
$conf['auth']['driver'] = 'oidc';

// Optional: auto-redirect to a specific provider on login.
// Must match the Provider ID configured in the admin UI.
$conf['auth']['params']['redirect_provider'] = 'cas-univ';
```

When `redirect_provider` is set, `login.php` skips the username/password
form and redirects directly to the provider.

### 3. Token storage

In the Horde admin UI under **Administration → Configuration → OAuth /
OIDC Tokens**, set the token driver to **SQL Database**. This is
required for any multi-worker or production deployment.

Alternatively, in `var/config/horde/conf.php`:

```php
$conf['oauth']['token_driver'] = 'sql';
// Uses the default Horde DB connection.
```

### 4. Provider setup

Go to **Administration → Authentication → OAuth Providers** and create
a new provider of type `oidc`.

Enter the Issuer URL and click **Auto-discover endpoints from issuer** —
Horde will populate all endpoints from the provider's
`.well-known/openid-configuration` document.

Then fill in the **Credentials** section:

| Field | Value |
|---|---|
| Client ID | From your IdP's application console |
| Client Secret | From your IdP's application console |
| Default Scopes | `openid email profile` (adjust to your IdP) |

Copy the **Redirect URI** shown on the form into your IdP's application
configuration as the allowed callback URL.

#### OIDC-specific fields

| Field | Description |
|---|---|
| Logout Strategy | `local`, `slo` or `revoke_and_slo` (see below) |
| End Session Endpoint | Leave empty to use auto-discovery |
| Post-Logout Redirect URI | Where the IdP redirects after SLO |
| Back-Channel Username Claim | JWT claim identifying the user (default: `sub`) |
| Use email as XOAUTH2 username | Append a domain to the Horde username |
| XOAUTH2 Domain | Domain to append (e.g. `example.com`) |

### 5. Hooks

Activate the OIDC hooks by copying the relevant sections from the
`.dist` files to your deployed hooks files.

#### IMAP (`var/config/imp/hooks.php`)

Uncomment `imap_preauthenticate` to inject a XOAUTH2 token into each
IMAP connection:

```php
public function imap_preauthenticate(array $credentials): array
{
    global $injector;
    $username = $injector->getInstance('Horde_Registry')->getAuth();
    if (!$username) {
        return $credentials;
    }
    $tokenService   = $injector->getInstance(\Horde\Core\Service\OAuthTokenService::class);
    $providerConfig = $injector->getInstance(\Horde\Core\Service\OAuthProviderConfigRepository::class);
    $row = \Horde\Core\Service\OidcHookHelper::findProviderForUser(
        $username, $tokenService, $providerConfig
    );
    if ($row === null) {
        return $credentials;
    }
    $accessToken = \Horde\Core\Service\OidcHookHelper::getValidAccessToken(
        $username, $row, $tokenService, $injector
    );
    if ($accessToken === null) {
        return $credentials;
    }
    $xoauth2User = \Horde\Core\Service\OidcHookHelper::xoauth2Username($username, $row);
    $credentials['password'] = new Horde_Imap_Client_Password_Xoauth2(
        $xoauth2User, $accessToken
    );
    return $credentials;
}
```

Also uncomment `dynamic_prefs` to proactively refresh tokens before
they expire during a session (recommended):

```php
public function dynamic_prefs()
{
    global $injector;
    $tokenService   = $injector->getInstance(\Horde\Core\Service\OAuthTokenService::class);
    $providerConfig = $injector->getInstance(\Horde\Core\Service\OAuthProviderConfigRepository::class);
    $username = $injector->getInstance('Horde_Registry')->getAuth();
    if ($username) {
        $row = \Horde\Core\Service\OidcHookHelper::findProviderForUser(
            $username, $tokenService, $providerConfig
        );
        if ($row !== null) {
            try {
                $tokenSet = $tokenService->getTokenSet($username, $row['provider_id']);
                if ($tokenSet->isExpired(300)) {
                    $accessToken = \Horde\Core\Service\OidcHookHelper::getValidAccessToken(
                        $username, $row, $tokenService, $injector
                    );
                    if ($accessToken !== null) {
                        $xoauth2User = \Horde\Core\Service\OidcHookHelper::xoauth2Username($username, $row);
                        $xoauth2Obj  = new Horde_Imap_Client_Password_Xoauth2($xoauth2User, $accessToken);
                        $session     = $injector->getInstance('Horde_Session');
                        foreach (array_keys($_SESSION['imp'] ?? []) as $key) {
                            if (str_starts_with($key, 'IMP_Imap_Password/')) {
                                $session->set('imp', $key, $xoauth2Obj, $session::ENCRYPT);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }
    }
    return [];
}
```

#### SMTP (`var/config/horde/hooks.php`)

Uncomment `smtp_credentials`:

```php
public function smtp_credentials(string $username): array
{
    global $injector;
    $tokenService   = $injector->getInstance(\Horde\Core\Service\OAuthTokenService::class);
    $providerConfig = $injector->getInstance(\Horde\Core\Service\OAuthProviderConfigRepository::class);
    $row = \Horde\Core\Service\OidcHookHelper::findProviderForUser(
        $username, $tokenService, $providerConfig
    );
    if ($row === null) {
        throw new Horde_Exception_HookNotSet();
    }
    $accessToken = \Horde\Core\Service\OidcHookHelper::getValidAccessToken(
        $username, $row, $tokenService, $injector
    );
    if ($accessToken === null) {
        throw new Horde_Exception_HookNotSet();
    }
    $xoauth2User = \Horde\Core\Service\OidcHookHelper::xoauth2Username($username, $row);
    return [
        'xoauth2_token' => new Horde_Smtp_Password_Xoauth2($xoauth2User, $accessToken),
        'username'      => $xoauth2User,
    ];
}
```

#### Sieve / Ingo (`var/config/ingo/hooks.php`)

Uncomment the `timsieved` case in `transport_credentials`. See
`horde/ingo/config/hooks.php.dist` for the full example.

## Logout strategies

The logout strategy is configured per provider in the admin UI under
**Logout Strategy**.

| Strategy | Behaviour |
|---|---|
| `local` | Tokens are removed from local storage only. The IdP session remains active. |
| `slo` | Tokens are removed locally, then the browser is redirected to the IdP's `end_session_endpoint`. |
| `revoke_and_slo` | Tokens are revoked at the provider's revocation endpoint, then SLO redirect. |

The SLO redirect URL is resolved in order:
1. `end_session_endpoint` field on the provider
2. Auto-discovery from `{issuer}/.well-known/openid-configuration`

Configure `post_logout_redirect_uri` to control where the IdP redirects
the browser after SLO. Typically this should be your Horde login page:

```
https://webmail.example.org/horde/login.php
```

## Back-channel logout (RFC 9470)

Horde implements OpenID Connect Back-Channel Logout 1.0. When the IdP
terminates a user's session (e.g. because they logged out of another
application), it sends a signed `logout_token` JWT to:

```
POST /horde/auth/oidc/backchannel-logout
```

Horde validates the token (signature, issuer, audience, expiry, `jti`
replay protection, event URI) and removes the user's tokens from the
repository. On the user's next request, `validateAuth()` finds no tokens
and terminates the Horde session.

Configure this URL in your IdP as the back-channel logout URL. For
Apereo CAS:

```
cas.properties:
  cas.logout.enabled=true
  cas.slo.disabled=false
```

And in the registered service:
```json
{
  "logoutType": "BACK_CHANNEL",
  "logoutUrl": "https://webmail.example.org/horde/auth/oidc/backchannel-logout"
}
```

### JWT validation

| Check | Detail |
|---|---|
| Signature | RS256 or ES256 via provider JWKS |
| `iss` | Must match configured provider issuer |
| `aud` | Must match configured client ID |
| `exp` | Must not be expired (±5 min clock skew) |
| `iat` | Must be recent (±5 min) |
| `jti` | Must not have been seen before (1h replay cache) |
| `events` | Must contain the BCL event URI |
| `nonce` | Must be absent |

JWKS keys are cached for one hour.

## Troubleshooting

**Users are redirected to login immediately after authenticating**

Check that the SQL token driver is configured and the migration has run.
If `validateAuth()` finds no tokens it terminates the session.

**Important:** The `Builtin` session handler is incompatible with the
modern `HordeSession` stack — sessions are written in memory but never
persisted to disk, causing every request to appear unauthenticated.
Set `$conf['sessionhandler']['type'] = 'Sql'` in `conf.php` for any
production deployment.

**IMAP authentication fails**

Verify that the IMAP server supports XOAUTH2 and that the
`imap_preauthenticate` hook is active. Check that the access token
contains the claim expected by the IMAP server's SASL plugin (often
`email` or `sub` — configure `oauth2_user_claim` in the SASL plugin
accordingly).

**SMTP authentication fails with "User claim not found"**

The SASL XOAUTH2 plugin on the SMTP server is looking for a claim
(typically `email`) that is absent from the access token. Set
`oauth2_user_claim: sub` (or the appropriate claim) in the SASL
configuration on the SMTP server.

**Back-channel logout has no effect**

Look for `[OidcBCL]` entries in the Horde log. The endpoint must be
reachable without authentication — verify the route middleware stack
does not include `RedirectToLogin`.

**SLO redirect does not happen**

Ensure the provider's `end_session_endpoint` is either configured
explicitly or discoverable via `.well-known/openid-configuration` from
the Horde server. Check for `[OidcPreLogout]` entries in the PHP error
log.
