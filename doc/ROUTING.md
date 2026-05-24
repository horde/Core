# Routing Architecture

Horde 6 defines, loads and generates URLs for app webroots, static artifacts, javascript, themes, global services and application pages from the Routes system.
Routes can be generated on-the-fly per request by RuntimeRoutesProvider or precompiled by an upcoming CompiledRoutesProvider which sets up all routes during install/update/reconfigure.

This document describes the details.

## Overview

All URL generation flows through a single interface:

```
RoutesProvider::generateNamedPath(string $routeName, array $params = []): ?string
```

The runtime implementation is `RuntimeRoutesProvider` (a `GroupMapper`).
A future compiled implementation will serve production from opcached arrays.

Controllers and views consume `RouteUrlWriter` (wraps `RoutesProvider` with
scheme/host qualification) or `RoutesProvider` directly.

## Route Sources (load order)

`RuntimeRoutesProvider::loadAllApps()` loads routes in this order:

### 1. Per-App Group Routes (`config/routes.php`)

Each active application provides a `config/routes.php` that defines routes
within a group context. The group prefix is the app's `webroot` from registry.

```php
// whups/config/routes.php
// Group prefix "/whups" is applied automatically — routes are relative.

$mapper->buildRoute(uri: '/ticket/:id', name: 'TicketView')
    ->withController(Controller\Ticket\ViewController::class)
    ->withRequirements(['id' => '\d+'])
    ->withMiddleware($fatStack)
    ->add();
```

The group also carries:
- `host` — if the app runs on a different domain
- `scheme` — if the app forces HTTPS
- `port` — if non-standard
- `defaults` — `['app' => $appName]` on every route

These values are baked into each `Route` object at definition time.
`Route::generate()` returns the full path including prefix.

### 2. Per-App Local Override (`routes.local.php`)

Admin-provided route overrides, loaded after the app's own routes.

Canonical location: `HORDE_CONFIG_BASE/{app}/routes.local.php`

These run inside the same group context (same prefix, defaults) as the
app's primary `routes.php`. Use cases:

- Override a route's controller (e.g. custom ticket view)
- Add deployment-specific routes (e.g. health checks)
- Disable a route (planned: explicit `removeRoute(name:)` or `->disable()` API;
  not yet implemented in GroupMapper)

### 3. Global Routes (`HORDE_CONFIG_BASE/routes.php`)

A single root-level routes file loaded outside any group context.
No prefix, no app default. Routes defined here are absolute paths.

Use cases:

- Cross-app redirects
- Reverse proxy health endpoints
- Custom vanity URLs that don't belong to any app

### 4. Registry-Derived System Routes

After all route files are loaded, `registerSystemRoutes()` creates named
routes from registry configuration. These exist purely for URL generation
(no controller, no middleware attached). Routes defined in app `routes.php`,
`routes.local.php`, or global `routes.php` take precedence — system routes
are only registered if the name is not already claimed.

| Named Route | Source | Example Path |
|-------------|--------|--------------|
| `{App}Home` | `webroot` per app | `/imp`, `/whups` |
| `{App}Js` | `jsuri` per app (only if set) | `/js/imp` |
| `{App}Themes` | `themesuri` per app (only if set) | `/themes/imp` |
| `HordeStatic` | `staticuri` from horde app (only if set) | `/static/` |

Naming convention: `ucfirst($app)` + suffix.
Examples: `ImpHome`, `WhupsJs`, `ImpThemes`, `HordeStatic`.

No fallbacks are generated for missing registry values. If `jsuri` or
`themesuri` is absent, the corresponding named route is not registered
and `generateNamedPath()` returns `null`. This signals an incomplete
configuration rather than silently producing a wrong URL.

#### Service Routes

Named routes for horde's global service endpoints, derived from
horde's `webroot`. Schema: `HordeServices` + `PascalCaseName`.

| Named Route | Path (relative to horde webroot) |
|-------------|----------------------------------|
| `HordeServicesAjax` | `/services/ajax.php` |
| `HordeServicesCache` | `/services/cache.php` |
| `HordeServicesDownload` | `/services/download` |
| `HordeServicesConfirm` | `/services/confirm.php` |
| `HordeServicesGo` | `/services/go.php` |
| `HordeServicesHelp` | `/services/help` |
| `HordeServicesImple` | `/services/imple.php` |
| `HordeServicesLogin` | `/login.php` |
| `HordeServicesLogintasks` | `/services/logintasks.php` |
| `HordeServicesPortal` | `/services/portal` |
| `HordeServicesPrefs` | `/services/prefs.php` |
| `HordeServicesProblem` | `/services/problem.php` |
| `HordeServicesSidebar` | `/services/sidebar.php` |
| `HordeServicesRpc` | `/rpc` |

These routes exist for URL generation. Controllers may be attached to
them later as the legacy `.php` endpoints migrate to PSR-15 handlers.

## Compilation

`RuntimeRoutesProvider` calls `compile()` after all routes are loaded.
This populates the internal match list. A future production mode will
serialize the compiled state to a PHP file for opcache.

## Consuming Routes

### In PSR-15 Controllers (preferred)

Inject `RouteUrlWriter`:

```php
class MyController implements RequestHandlerInterface
{
    public function __construct(
        private readonly RouteUrlWriter $urlWriter,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var string|null "/whups/ticket/42" */
        $ticketUrl = $this->urlWriter->urlFor('TicketView', ['id' => '42']);

        /** @var string|null "https://horde.example.com/whups/ticket/42" */
        $absoluteUrl = $this->urlWriter->absoluteUrlFor('TicketView', ['id' => '42']);
    }
}
```

### In View Helpers

Inject `RoutesProvider` and generate paths:

```php
$path = $this->provider->generateNamedPath('WhupsHome');
```

### In Legacy Code

`RoutesProvider` is available from the injector:

```php
$provider = $injector->getInstance(RoutesProvider::class);
$path = $provider->generateNamedPath('ImpHome');
```

## Injector Bindings

| Interface/Class | Factory | Notes |
|----------------|---------|-------|
| `RoutesProvider` | `RuntimeRoutesProviderFactory` | The primary interface |
| `RuntimeRoutesProvider` | `RuntimeRoutesProviderFactory` | Concrete class (same instance) |
| `RouteUrlWriter` | `RouteUrlWriterFactory` | Wraps RoutesProvider + environ |
| `Horde\Routes\Mapper` | `Horde_Core_Factory_Mapper` | Legacy — for old lib/ code only |

In the Rampage path, `RampageBootstrap` sets instances directly (factory
not invoked). In the legacy path, `RuntimeRoutesProviderFactory` builds
`RegistryState` from `RegistryConfigLoader` and creates a
`ServerRequestInterface` from globals.

## Route Definition API

Routes are defined using the fluent `RouteBuilder` API on `GroupMapper`:

```php
$mapper->buildRoute(uri: '/path/:param', name: 'RouteName')
    ->withController(MyController::class)       // PSR-15 handler
    ->withMiddleware($stack)                    // middleware pipeline
    ->withDefaults(['action' => 'index'])       // default route params
    ->withRequirements(['param' => '\d+'])      // regex constraints
    ->withSecondaryRoute('/legacy.php')         // alias for old URLs
    ->add();
```

Key points:
- `name` is mandatory — unnamed routes cannot be used for URL generation
- `uri` is relative to the group prefix (the app's webroot)
- `withSecondaryRoute` creates an alias that matches but doesn't generate
- System routes (from `registerSystemRoutes`) use `buildRoute` without
  controller or middleware — they are generation-only

## File Locations

```
{app}/config/routes.php              — app route definitions
HORDE_CONFIG_BASE/{app}/routes.local.php — deployment override
HORDE_CONFIG_BASE/routes.php         — global root-level routes
Core/src/RuntimeRoutesProvider.php   — runtime loader + system routes
Core/src/Uri/RoutesProvider.php      — interface
Core/src/Uri/RouteUrlWriter.php      — URL writer (controllers inject this)
Core/src/Factory/RuntimeRoutesProviderFactory.php — DI factory
```
