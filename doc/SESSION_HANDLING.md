# Horde\Core Session Handling

> **Audience.** Application developers writing routes, controllers or
> middleware against horde/Core. LLMs navigating Horde's two coexisting
> session models. Framework contributors deciding where new behaviour
> belongs.
>
> **Out of scope.** Detailed JWT generation/verification (see
> `src/Auth/Jwt/`), credential storage (see `AuthCredentialStore`),
> backend storage drivers (see horde/SessionHandler).

Session handling in horde/Core and applications based on it follows two
different fundamental flows. Pick the right one for the route you are
writing. Do not mix patterns within a single route.

## Implied Flow (legacy)

The "implied" flow is the legacy flow used by most existing code. It
applies both to classic entrypoint scripts (`horde/services/portal.php`,
`imp/index.php`) and all routes-based flows using the `HordeCore`
middleware or relying on the legacy `Horde_Registry`.

In this flow, some part of the call chain invokes
`Horde_Registry::appInit` which bootstraps the globals environment used
by most legacy code and implicitly sets up a session, even for
unauthenticated pages, for guests and for REST/RPC calls.

Code which relies on the globals (including the global `$session`,
`$registry`, `$injector`, `$conf`, `$prefs`, `$language`, `$notification`)
will work. Code generally does not care about persisting the session.
The `SessionHandler` is installed as a `Horde_Shutdown` task and
persists automatically at request shutdown. This means in the Rampage
Routes & Middleware stack, sessions are written after the full HTTP
response has been emitted and all middlewares have executed.

The implied flow is correct for any controller that:

- Reads or writes preferences, identities, app credentials, or
  notifications in the per-request manner Horde apps have always done.
- Reaches into `$GLOBALS['injector']->getInstance('Horde_Prefs')` or
  similar bindings that Registry sets up at auth time.
- Renders a Horde view template, since `Horde_View` and
  `Horde_PageOutput` consume the same globals.

## Explicit Flow (modern)

In the explicit flow session control does not happen behind the scenes.
If no session is ever requested, none will be initiated.

A route decides if it wants sessions at all by adding a middleware
early in the stack which loads sessions from a JWT's `jti` property or
a session-cookie header. The same middleware persists the session on
the way out when the response bubbles up through the middleware stack.

Alternatively a route can have no session-loading middleware and let
the controller decide if and when it wants to establish, recover,
modify, store, rotate or destroy a session.

The implication of this explicit flow: there is no `$session` global
and the other Horde globals do not exist either unless the route took
care of setting them up. Many factories and objects designed for the
rich legacy environment will not work.

Explicit session handling (and the wider implication of only setting
up what the route asks for) enables a much leaner design with fewer
surprising side effects. Use it for:

- Pure JSON APIs where the response shape is the contract and you do
  not want template engines or notification handlers in the way.
- Health checks, readiness probes and other endpoints where any
  side-effect during the request is undesirable.
- Token-authenticated endpoints where session cookies are noise.
- Any new route where you want to keep dependencies explicit.

## Globals-Free Middleware Stack for JWT+Session

The canonical example is the `whoami` demo route in horde/base:

```php
// base/config/routes.php
$mapper->buildRoute(uri: '/api/v1/session/whoami', name: 'SessionWhoami')
    ->withController(Service\SessionWhoamiController::class)
    ->withDefaults(['HordeAuthType' => 'NONE'])
    ->withMiddleware([
        ErrorFilter::class,
        JwtSessionLoader::class,
        HordeSessionMiddleware::class,
    ])
    ->withMethods(['GET'])
    ->add();
```

The stack is, top to bottom:

1. `ErrorFilter` catches uncaught exceptions and converts them to JSON
   error responses with appropriate status codes.
2. `JwtSessionLoader` reads the `horde_jwt_refresh` cookie if present.
   When the cookie carries a valid refresh token, the loader extracts
   the `jti` claim, calls `SessionHandler::load($jti)` and sets the
   resulting `HordeSession` as the `session` request attribute. When
   the cookie is missing, malformed, expired or points at a non-
   existent row, the loader is a no-op and the session attribute is
   left unset.
3. `HordeSessionMiddleware` checks whether the session attribute is
   already set (by `JwtSessionLoader` or any earlier middleware). If
   set, the existing session is honoured. If not, the middleware reads
   the regular session cookie (`Horde` by default), loads the row, or
   mints a fresh one. Either way, the request attribute carries a
   `HordeSession` by the time the controller sees it.
4. The controller reads the session attribute via
   `$request->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION)`
   and uses the typed `HordeSession` API.
5. On the way out, `HordeSessionMiddleware` persists the session row
   (when dirty), processes any `markDestroyed()` /
   `scheduleRegeneration()` intent flags, and emits the appropriate
   `Set-Cookie` header.

### Authentication versus identity

Neither `JwtSessionLoader` nor `HordeSessionMiddleware` performs
authentication. They load whatever session the request hands them.
Whether the loaded session represents an authenticated user is a
question for the controller, which inspects
`$session->getAuthenticatedUser()` and decides what to do.

Stacks that want to demand authentication add `DemandAuthenticatedUser`
or a similar middleware after the session-loading layer. That
middleware short-circuits with a 401 when `getAuthenticatedUser()`
returns null. The whoami route is gentler: it returns 401 with a
documented JSON body when anonymous and a populated body when
authenticated, both as 200 OK from the controller perspective.

### Who establishes the session row

The first request always lands without a `JwtSessionLoader` match (no
cookie yet) and `HordeSessionMiddleware` mints a fresh session via
`SessionHandler::create()`. The fresh session is anonymous: no
`auth/userId`, empty `auth_app_*` slots. A subsequent login call
(through `LoginService`, the legacy `Horde_Registry::setAuth` or any
other authenticator) writes the auth slots into the SAME session row.
The cookie carries the same id across the auth transition.

If you want the auth transition to also rotate the id (good practice
to defeat session fixation), the controller calls
`$session->scheduleRegeneration()` after writing the auth slots. The
middleware acts on the marker before emitting the response and the
new id lands in the next `Set-Cookie`.

## Truely Session-Less Routes

The simplest example is the readiness probe in horde/base:

```php
$mapper->buildRoute(uri: '/observability/readiness', name: 'Readiness')
    ->withDefaults(['HordeAuthType' => 'NONE'])
    ->withMiddleware([Observability\Readiness::class])
    ->add();
```

No controller. No session middleware. The readiness middleware itself
returns a fixed `1` body and never touches session state. The route
exists to answer the question "is the server up enough to serve
HTTP" without paying for any session bookkeeping.

A token-authenticated admin route is the next step up. It
authenticates the caller via `Authorization: Bearer <admin-secret>`
or a JWT access token, but does not need a session row:

```php
$mapper->buildRoute(uri: '/api/v1/admin/diagnostic', name: 'AdminApiDiagnostic')
    ->withController(Admin\DiagnosticController::class)
    ->withDefaults(['HordeAuthType' => 'NONE'])
    ->withMiddleware([
        ErrorFilter::class,
        JwtAuthMiddleware::class, // verifies Authorization: Bearer <access-token>
    ])
    ->withMethods(['GET'])
    ->add();
```

Note that this is `JwtAuthMiddleware`, not `JwtSessionLoader`. The two
have similar names but very different jobs:

- `JwtSessionLoader`: reads the `horde_jwt_refresh` *cookie*, looks up
  a session row by JTI, hands a `HordeSession` to the rest of the
  stack. It is for browser-style flows where the JWT and the session
  are two transports into one store.
- `JwtAuthMiddleware`: reads an `Authorization: Bearer` header,
  verifies the access token, sets `verified_jwt` and `user_id`
  request attributes. It does NOT load or mint a session. It is for
  pure API consumers who never want a session row.

If a route uses `JwtAuthMiddleware` only, no session is ever loaded or
created. The controller cannot call `$request->getAttribute('session')`
because nothing put it there.

For routes that need both a session AND token-authenticated callers
(rare, but possible: imagine a script that first authenticates via
Bearer token, then needs a short-lived session row to coordinate with
a websocket), stack `JwtAuthMiddleware` followed by
`HordeSessionMiddleware` with `cookieDisabled: true` set on its
`SessionConfig`. The session row exists. No cookie ever leaves the
server.

## HordeCore-Based Flow with Globals

The default middleware stack used by most existing routes pulls in the
full legacy environment:

```php
$mapper->buildRoute(uri: '/admin/', name: 'AdminDashboard')
    ->withController(Admin\AdminDashboardController::class)
    ->withDefaults(['HordeAuthType' => 'authenticate'])
    ->withMiddleware([
        HordeCore::class,
        ErrorFilter::class,
        AuthHordeSession::class,
        DemandAuthenticatedUser::class,
    ])
    ->add();
```

Here `HordeCore` middleware calls
`Horde_Registry::appInit('horde', ['authentication' => 'none'])` which:

- Bootstraps `$GLOBALS['registry']`, `$GLOBALS['injector']`,
  `$GLOBALS['conf']`, `$GLOBALS['prefs']`, `$GLOBALS['language']`,
  `$GLOBALS['notification']`, and the per-app prefs binding.
- Registers the legacy `Horde_Session` shim under
  `$GLOBALS['session']`.
- Opens the PHP session via the shim's `setup()`, which delegates to
  `SessionLifecycle::setup()` for the actual save-handler registration
  and `session_start()`.

`AuthHordeSession` middleware then reads the auth slot from
`$_SESSION` (mirrored from the modern session by the shim's `addFinal`
task) and short-circuits to a redirect or 401 when the session has no
authenticated user.

`DemandAuthenticatedUser` is a final guard that fires if any earlier
layer left an unauthenticated request through.

Use this stack for:

- Any controller that renders a Horde view template.
- Any controller that needs `Horde_Prefs`, `Horde_Identity` or other
  per-user resources resolved through the legacy bindings.
- Any controller that delegates to legacy app methods via
  `$registry->callAppMethod()`.

The trade-off is that every globals consumer the stack pulls in becomes
part of the route's effective dependency surface. Modern routes that
want a minimal surface use the explicit flow above.

## Individual Components

- `Horde\Core\Session\HordeSession`: request-scoped data layer.
  Two-level scoped storage (`$data[$app][$name]`), plus a flat top
  level for framework-internal markers (`_b` begin timestamp, `_r`
  regeneration deadline). Carries lifecycle intent flags
  (`scheduleRegeneration`, `markDestroyed`) that the engine acts on.
- `Horde\Core\Session\SessionLifecycle`: engine. Wraps the modern
  `Horde\SessionHandler\SessionHandler` plus the legacy PHP
  session-module bootstrap (cookie params, ini tuning, save handler
  registration, `session_start`). Synchronous executors: `clean()`,
  `destroy()`, `regenerate()`. Engine method: `processFlags()` reads
  HordeSession's intent markers and dispatches to the matching
  executor. Always called explicitly.
- `Horde\Core\Session\SessionConfig`: typed view of session-related
  Horde config keys. Built from `ConfigLoader` state by
  `SessionConfigFactory`. Immutable.
- `Horde\Core\Middleware\HordeSessionMiddleware`: modern PSR-15
  middleware. Loads session via `SessionHandler::load`, mints a fresh
  one when no cookie or invalid cookie, sets a `session` request
  attribute, persists on the way out, emits `Set-Cookie`.
- `Horde\Core\Middleware\JwtSessionLoader`: modern PSR-15 middleware.
  Reads JWT refresh cookie, verifies, looks up the session by JTI
  (which IS the session id in the JWT-JTI architecture), sets the
  same `session` request attribute. Composes with
  `HordeSessionMiddleware` (the latter honours an already-set
  attribute).
- `Horde\Core\Middleware\JwtAuthMiddleware`: modern PSR-15 middleware.
  Reads `Authorization: Bearer <access-token>`, verifies the access
  token, sets `verified_jwt` and `user_id` request attributes. Does
  NOT load a session.
- `Horde_Session` (`lib/Horde/Session.php`): legacy shim. Delegates
  lifecycle work to `SessionLifecycle`. Carries the `addFinal` mirror
  task for legacy callers that read `$_SESSION` directly.

## Lifecycle Markers

`HordeSession` carries two private bool flags set by intent setters
and read by the engine:

- `markDestroyed()` / `isDestroyed()`: request the session be torn
  down at end of request.
- `scheduleRegeneration()` / `shouldRegenerate()`: request the
  session id rotate.
- `clearLifecycleFlags()`: `@internal`. Called by the engine after
  acting on the markers.

Markers are runtime-only. They are never persisted.

`SessionLifecycle::processFlags(HordeSession)` is the canonical
reader. Synchronous executors clear flags after acting so a
downstream `processFlags()` call is a coherent no-op.

In modern routes, `HordeSessionMiddleware` reads the markers on
response emit (NOT via `processFlags`, which couples to PHP's session
module that the modern middleware avoids) and dispatches to
`SessionHandler::regenerate` / `SessionHandler::destroySession`
directly. Same outcome, different code path.

## Cookie Emission Contract

`HordeSessionMiddleware` emits `Set-Cookie` on three paths:

- **Fresh mint:** request had no cookie or an invalid one. Emit
  `Set-Cookie: <name>=<id>; ...` with the freshly minted id.
- **Rotation:** controller called `scheduleRegeneration()`. Emit the
  cookie with the new id. The old row is deleted by
  `SessionHandler::regenerate`.
- **Destruction:** controller called `markDestroyed()`. Emit a
  clearing cookie (`Max-Age=0`, empty value).

Steady-state requests (cookie present, id unchanged, no rotation, no
destruction) do NOT re-emit the cookie.

### `cookieDisabled` mode

`SessionConfig::cookieDisabled` (default false). When true,
`HordeSessionMiddleware` suppresses Set-Cookie on every path. The
session row is still loaded and saved. Only the wire-level cookie
transport is disabled. Used by pure API routes that identify clients
via `Authorization: Bearer` tokens.

`SessionConfigFactory` does not read this from configuration. Opt in
by constructing `SessionConfig` directly as a route-specific override.

## `Horde_Registry::clearAuth()`

Wipes all auth-prefixed slots from the `horde` scope. Prefixes:

- `auth/` (Registry::setAuth flat slots: userId, credentials,
  timestamp, browser, remoteAddr, authId)
- `auth_app/` (AuthCredentialStore credentials)
- `auth_app_init/` (AuthCredentialStore init flag)
- `auth_app_state/` (HasCredentialsState)
- `auth_app_state_at/` (state transition timestamps)
- `auth_app_state_reason/` (InvalidationReason)
- `auth_app_state_detail/` (state detail message)

Keep this list synchronised when new slot families are added to
`AuthCredentialStore`.

When `clearAuth($destroy=true)`, the method calls
`SessionLifecycle::destroy()` directly (via injector) to tear down the
backend row, then marks the rebuilt HordeSession destroyed so
`HordeSessionMiddleware` emits `Set-Cookie: cleared` on response.

The pre-Gap-14 implementation used `removeScoped('horde', 'auth')`
which removed a literal key named `'auth'` (no slash). Every actual
auth slot survived. The legacy stack masked the bug via `appInit`'s
own auth-state checks. Modern PSR-15 routes that load the session
via `SessionHandler::load` exposed it.

## On-Disk Format

The default session-data serializer is the pure-PHP
`Horde\SessionHandler\PhpTextSessionSerializer` (or
`PhpSessionSerializer` if PHP's `session.serialize_handler` is
`php_serialize`). Both produce the exact byte sequence PHP's session
module produces, so legacy and modern stacks share storage.

Modern PSR-15 routes read and write session rows without ever calling
`session_start()`. The legacy stack continues to go through the PHP
session module via the `Horde_Session` shim. A single backend row
serves both flows.

## How to Pick a Flow

| Question | Implied flow | Explicit flow |
|----------|--------------|---------------|
| Renders a Horde view template? | yes | no |
| Reads `$registry->callAppMethod()`? | yes | no |
| Uses `Horde_Prefs` directly? | yes | refactor: inject `PrefsService` |
| Uses `Horde_Notification`? | yes | inject `Horde_Notification_Handler` |
| Pure JSON API? | works but heavy | preferred |
| Token-only authentication, no cookie? | wrong fit | preferred |
| Health / readiness probe? | wrong fit | preferred |
| New code? | only when the existing app demands it | default |

Mixing rule: a route either pulls in `HordeCore` middleware or it does
not. If it does, every controller behind it can assume the legacy
globals exist. If it does not, the controller must take its
collaborators via constructor injection through a `#[Factory]` and
must read request-scoped state from PSR-7 attributes.

## Common Pitfalls

- **Reading `$_COOKIE` from a controller.** Use
  `$request->getCookieParams()` from PSR-7 instead. The legacy stack
  populates `$_COOKIE` via PHP's normal request handling, but the
  modern stack accepts request injection from places PHP's
  superglobals do not see.
- **Calling `session_id()` or `session_name()` from controller code.**
  Use `(string) $session->getId()` (where `$session` came from the
  request attribute) and `$sessionConfig->cookieName` instead. The
  legacy `session_*()` functions only return values when PHP's session
  module is active, which is not the case in the explicit flow.
- **Resolving `AuthCredentialStore` via DI from a modern controller.**
  The DI binding hands you an `AuthCredentialStore` keyed to the
  HordeSession that `HordeSessionFactory` produced at request start
  (typically empty). You want one keyed to the session the middleware
  loaded onto the request. Construct it locally:
  `new AuthCredentialStore($request->getAttribute('session'))`.
- **Mixing `JwtSessionLoader` with `JwtAuthMiddleware` and expecting
  one to feed the other.** They read different sources (refresh
  cookie vs `Authorization` header) and write different attributes
  (`session` vs `verified_jwt` and `user_id`). Use one or the other
  per route.
- **Calling `$session->markDestroyed()` and expecting the cookie to
  clear in legacy stacks.** `markDestroyed` only fires when
  `HordeSessionMiddleware` is in the response chain. In legacy
  flows, call `$registry->clearAuth(true)` instead, which performs
  the synchronous destruction AND sets the marker for any modern
  middleware that happens to also be present.

## See Also

- `horde-development/strategies/modern-session-migration/purely-modern-route-lifecycle-2026-06-10.md`
  Master plan and gap status.
- `horde-development/archive/completed-projects/session-unification-plan-2026-06-11.md`
  Three-commit unification of `SessionLifecycle`, the shim and the
  engine.
- `horde-development/archive/completed-projects/session-whoami-demo-plan-2026-06-11.md`
  Modern-stack end-to-end demo. The whoami controller in horde/base
  is the canonical worked example.
- `src/Auth/Jwt/` for JWT generation and verification details.
- `vendor/horde/sessionhandler/` for backend driver details
  (Sql, Builtin, File, Hashtable, Stack).
