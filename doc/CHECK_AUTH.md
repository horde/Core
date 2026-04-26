# Checking Credentials Without Logging In

Horde's `Horde_Core_Auth_Application` wraps the configured auth backend
(SQL, LDAP, IMAP, etc.) and manages session state on successful login.
Sometimes you need to verify that a username/password pair is valid
**without** starting a session, modifying globals or touching the
registry — for example in token-exchange API endpoints, admin tools that
test accounts or middleware that gates access to stateless resources.

## The `checkCredentials()` method

`Horde_Core_Auth_Application` exposes a dedicated method:

```php
use Horde\Core\Auth\CredentialCheckResult;

/** @var Horde_Core_Auth_Application $auth */
$result = $auth->checkCredentials($userId, ['password' => $password]);

if ($result === CredentialCheckResult::Valid) {
    // credentials are good — no session was created
}
```

The method:

1. Runs the `preauthenticate` hook (same as a normal login).
2. Delegates to the underlying base driver's `authenticate()` which
   performs lock checking, bad-login tracking and the actual credential
   validation.
3. Does **not** call `_setAuth()` — no session is created, no registry
   state is changed, no view mode is set.
4. Returns a `CredentialCheckResult` enum instead of a boolean.

### `CredentialCheckResult`

A backed enum in `Horde\Core\Auth\CredentialCheckResult` with four cases:

| Case | Meaning |
|---|---|
| `Valid` | Credentials are correct |
| `Invalid` | Wrong password or unknown user |
| `Locked` | Account locked (too many failures or admin lock) |
| `Expired` | Credentials expired (forced password change required) |

The enum also provides a factory method `fromAuthReason(int $reason)`
that maps `Horde_Auth::REASON_*` constants to the appropriate case.

## The `$login` parameter on `authenticate()`

`Horde_Auth_Base::authenticate()` has always accepted a `$login`
parameter documented as controlling whether a session is established.
Previously this parameter was accepted but ignored — authentication
always established session state.

`Horde_Core_Auth_Application::authenticate()` now honours it:

```php
// Full login (existing behaviour, unchanged)
$auth->authenticate($userId, $credentials);
$auth->authenticate($userId, $credentials, true);

// Credential check only — returns bool for backward compatibility
$auth->authenticate($userId, $credentials, false);
```

When `$login` is `false`, `authenticate()` delegates to
`checkCredentials()` internally and returns `true` if the result is
`Valid`, `false` otherwise. Use this form when you need a simple boolean
and don't need to distinguish between failure reasons.

## In-process caching

On a successful check the base driver stores the validated credentials
in its internal `$_credentials` array (an in-process, per-request
structure — not a persistent cache). If a subsequent full
`authenticate()` call happens for the same user in the same request,
backends that support it can short-circuit the validation. No passwords
are written to session files or external caches.

## `CheckCredentials` middleware

For the PSR-15 middleware stack, `Horde\Core\Middleware\CheckCredentials`
provides stateless HTTP Basic credential validation:

```
Authorization: Basic base64(user:password)
```

It parses the header, calls `checkCredentials()` on the configured auth
driver and sets two request attributes:

| Attribute | Type | When set |
|---|---|---|
| `HORDE_CREDENTIAL_CHECK` | `CredentialCheckResult` | Always (when Basic header present) |
| `HORDE_VERIFIED_USER` | `string` | Only when credentials are valid |

`HORDE_VERIFIED_USER` is intentionally distinct from
`HORDE_AUTHENTICATED_USER` (set by `AuthHordeSession` and
`AuthHttpBasic`). Downstream handlers can distinguish between a fully
logged-in user and one whose credentials have merely been verified.

### Middleware wiring

The middleware is injectable via `Horde\Injector\Attribute\Factory`:

```php
use Horde\Core\Middleware\CheckCredentials;

// The injector resolves it through CheckCredentialsFactory
$middleware = $injector->getInstance(CheckCredentials::class);
```

In a route definition that only needs credential verification (no
session):

```php
$route->middleware([
    CheckCredentials::class,
    // ... your handler
]);
```

### Example: token exchange endpoint

A typical use is an endpoint that accepts Basic credentials and returns
a JWT without creating a session:

```php
use Horde\Core\Auth\CredentialCheckResult;

class TokenExchangeHandler implements RequestHandlerInterface
{
    public function __construct(
        private JwtService $jwt,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $result = $request->getAttribute('HORDE_CREDENTIAL_CHECK');
        $user = $request->getAttribute('HORDE_VERIFIED_USER');

        if ($result !== CredentialCheckResult::Valid || $user === null) {
            return $this->errorResponse($result);
        }

        $token = $this->jwt->generateAccessToken($user);
        // return JSON response with token ...
    }

    private function errorResponse(?CredentialCheckResult $result): ResponseInterface
    {
        return match ($result) {
            CredentialCheckResult::Locked => /* 423 Locked */,
            CredentialCheckResult::Expired => /* 403 + password change URI */,
            default => /* 401 Unauthorized */,
        };
    }
}
```
