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
calls `setAuth()`. Validation on subsequent requests is per-app: apps
declaring the `validate` auth capability (e.g. IMP's
`IMP_Auth::validateOauth()`) check that the token is still present
before honoring the session. IMAP, SMTP, and Sieve connections use
XOAUTH2 via hooks.

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

OIDC/OAuth2 is independent of the Horde auth driver — authentication
itself continues to be handled by any existing driver (LDAP, SQL,
`application`, etc.). No dedicated `oidc` driver exists; leave
`$conf['auth']['driver']` set to whatever already authenticates your
users.

You can hide the username/password form and show only the configured
provider buttons on the login page:

```php
$conf['auth']['show_password_login'] = false;
```

**Warning:** this hides the password form for ALL users, regardless
of driver. If every user of this instance has a working OIDC/OAuth
login path, this is safe to enable — there is no per-user override.

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

### 5. Backend-scoped auto-login (IMP)

For transparent IMAP auto-login without password re-entry, declare
the OAuth provider directly on the backend in `backends.php`:

```php
$servers['imap']['oauth'] = 'cas-univ'; // matches a configured Provider ID
```

`_canAutoLogin()` reads this key directly and, when a valid token is
stored for the user, substitutes it for the password — no hook
required. A backend declaring `oauth` is presumed dedicated to OAuth
users: if the user has no token yet (e.g. never linked their account
under **Preferences → OAuth Accounts**), the connection attempt fails
rather than falling back to a password. Configure a separate backend
(with or without the same hostspec) for password-based access instead.

### 6. Hooks

Activate the OIDC hooks by copying the relevant sections from the
`.dist` files to your deployed hooks files.

- `dynamic_prefs` (`config/hooks.php.dist` in `imp`) proactively
  refreshes the token for IMAP connections that stay open longer than
  the token's lifetime — the connection-time XOAUTH2 substitution in
  `_canAutoLogin()` only runs once, at connection setup, so a token
  that expires mid-session needs this hook to stay valid.

- `smtp_credentials` (`config/hooks.php.dist` in `horde`) is the only
  way to wire OAuth into SMTP — there's no backend-scoped core config
  key for the mailer yet.

- The `timsieved` case in `transport_credentials`
  (`config/hooks.php.dist` in `ingo`) is the only way to wire OAuth
  into Sieve — same reasoning as SMTP.

All hook code ships commented out in the `.dist` files and must be
explicitly activated by the administrator.

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
Per-app validation (e.g. `IMP_Auth::validateOauth()` for IMAP backends
declaring an `oauth` provider) treats a missing token as invalid and
re-prompts for authentication.

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
