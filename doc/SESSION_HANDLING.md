# Horde\Core Session Handling

Building-block notes for the modern session-handling stack in horde/Core.
Not exhaustive. Each section is one decision or contract worth capturing
in source-controlled prose so it survives across refactors.

## Components

- `Horde\Core\Session\HordeSession`: request-scoped data layer. Two-level
  scoped storage (`$data[$app][$name]`), plus a flat top level for
  framework-internal markers (`_b` begin timestamp, `_r` regeneration
  deadline). Carries lifecycle intent flags
  (`scheduleRegeneration`, `markDestroyed`) that the engine acts on.
- `Horde\Core\Session\SessionLifecycle`: engine. Wraps the modern
  `Horde\SessionHandler\SessionHandler` plus the legacy PHP
  session-module bootstrap (cookie params, ini tuning, save handler
  registration, session_start). Synchronous executors:
  `clean()`, `destroy()`, `regenerate()`. Engine method: `processFlags()`
  reads HordeSession's intent markers and dispatches to the matching
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
- `Horde_Session` (lib/Horde/Session.php): legacy shim. Delegates
  lifecycle work to `SessionLifecycle`. Carries its own relogin
  guard and addFinal mirror task for legacy callers that read
  `$_SESSION` directly.

## Lifecycle markers

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

## Cookie emission contract

`HordeSessionMiddleware` emits `Set-Cookie` on three paths:

- **Fresh mint:** request had no cookie or an invalid one. Emit
  `Set-Cookie: <name>=<id>; ...` with the freshly minted id.
- **Rotation:** controller called `scheduleRegeneration()`. Emit
  the cookie with the new id. The old row is deleted by
  `SessionHandler::regenerate`.
- **Destruction:** controller called `markDestroyed()`. Emit a
  clearing cookie (`Max-Age=0`, empty value).

Steady-state requests (cookie present, id unchanged, no rotation,
no destruction) do NOT re-emit the cookie.

### `cookieDisabled` mode

`SessionConfig::cookieDisabled` (default false). When true,
`HordeSessionMiddleware` suppresses Set-Cookie on every path. The
session row is still loaded and saved. Only the wire-level cookie
transport is disabled. Used by pure API routes that identify
clients via `Authorization: Bearer` tokens.

`SessionConfigFactory` does not read this from configuration. Opt in
by constructing `SessionConfig` directly as a route-specific
override.

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
AuthCredentialStore.

When `clearAuth($destroy=true)`, the method calls
`SessionLifecycle::destroy()` directly (via injector) to tear down
the backend row, then marks the rebuilt HordeSession destroyed so
HordeSessionMiddleware emits `Set-Cookie: cleared` on response.

The pre-Gap-14 implementation used `removeScoped('horde', 'auth')`
which removed a literal key named `'auth'` (no slash). Every actual
auth slot survived. The legacy stack masked the bug via
`appInit`'s own auth-state checks. Modern PSR-15 routes that load
the session via `SessionHandler::load` exposed it.

## On-disk format

The default session-data serializer is the pure-PHP
`Horde\SessionHandler\PhpTextSessionSerializer` (or
`PhpSessionSerializer` if PHP's `session.serialize_handler` is
`php_serialize`). Both produce the exact byte sequence PHP's
session module produces, so legacy and modern stacks share storage.

Modern PSR-15 routes read and write session rows without ever
calling `session_start()`. The legacy stack continues to go through
the PHP session module via the `Horde_Session` shim.

## See also

- `horde-development/strategies/modern-session-migration/purely-modern-route-lifecycle-2026-06-10.md`
  Master plan and gap status.
- `horde-development/archive/completed-projects/session-unification-plan-2026-06-11.md`
  Three-commit unification of `SessionLifecycle`, the shim, and the engine.
- `horde-development/archive/completed-projects/session-whoami-demo-plan-2026-06-11.md`
  Modern-stack end-to-end demo.
