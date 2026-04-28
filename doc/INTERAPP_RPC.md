# Inter-App Communication and RPC

Horde 6 applications expose functionality to other apps and to external clients
through a new API system. The old H5-style API system is still active. Both can run side by side in the same
installation.

## Legacy APIs (Horde_Registry)

The original system. An application declares the interfaces it provides in its
`registry.d/` config snippet:

```php
// doc/registry.d/app-content.php
$this->applications['content']['provides'] = ['tagger'];
```

Methods live in a class extending `Horde_Registry_Api` (typically
`lib/Api.php`). Other apps call them through `Horde_Registry`:

```php
$registry->call('tagger/tag', [$userId, $objectId, $tags]);
```

Internally the registry uses **slash-separated** method names
(`tagger/tag`). RPC transports (XML-RPC, SOAP, legacy JSON-RPC) convert
slashes to dots on the wire (`tagger.tag`).

Legacy APIs depend on `Horde_Registry` for discovery, routing and app
bootstrapping. They have no structured metadata. There are no parameter descriptions beyond phpdoc,
no return types, no permission declarations.

## Modern APIs (ApiRegistry)

The modern system is built on dependency injection.
It lives in three packages:

| Package | Responsibility |
|---------|---------------|
| `horde/Rpc` | Interfaces and value objects (`Horde\Rpc\Dispatch\*`) |
| `horde/Core` | Central registry, factory, DI wiring (`Horde\Core\Api\*`) |
| App packages | Provider implementations (`Horde\{App}\Api\*`) |

### Core interfaces (horde/Rpc)

**`ApiProvider`** method discovery (introspection only):

```php
interface ApiProvider
{
    public function hasMethod(string $method, ?ApiCallContext $context = null): bool;
    public function getMethodDescriptor(string $method, ?ApiCallContext $context = null): ?MethodDescriptor;
    public function listMethods(?ApiCallContext $context = null): array;
}
```

**`MethodInvoker`** method execution:

```php
interface MethodInvoker
{
    public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result;
}
```

Every provider implements both interfaces. Separating them allows
introspection without execution (useful for `rpc.discover`, admin screens,
and MCP tool listings).

**`MethodDescriptor`** readonly metadata for a single method:

```php
final readonly class MethodDescriptor
{
    public function __construct(
        public string $name,
        public string $description = '',
        public array $parameters = [],
        public ?string $returnType = null,
        public ?array $inputSchema = null,
        public ?array $outputSchema = null,
        public array $permissions = [],
    ) {}
}
```

**`ApiCallContext`** immutable attribute bag carrying out-of-band context:

```php
$context = new ApiCallContext(['permissions' => ['admin'], 'userId' => $uid]);
```

Transports populate this with protocol-specific information (caller identity,
auth state, out of band data). Providers inspect it to filter method visibility or enforce
authorization. A null context means "vanilla response, no special
privileges."

**`Result`** simple wrapper for invocation return values:

```php
$result = new Result($value);
```

### The central registry (horde/Core)

`ApiRegistry` aggregates all per-app providers into one place. It implements
both `ApiProvider` and `MethodInvoker` itself, so it can
be used as the single entry point for any transport.

Method names at the registry level use **dot notation**: `interface.method`.
The registry splits on the first dot to route to the correct provider:

```
tagger.tag  ->  provider "tagger",  method "tag"
taggerAdmin.listTagsByUser  ->  provider "taggerAdmin", method "listTagsByUser"
```

Key methods:

```php
// Standard dispatch splits "interface.method" and delegates
$registry->invoke('tagger.tag', [$userId, $objectId, $tags], $context);

// Explicit dispatch when caller knows the app
$registry->invokeExplicit('content', 'tagger', 'tag', $params, $context);

// Introspection
$registry->listMethods($context);        // all methods, prefixed
$registry->hasMethod('tagger.tag');
$registry->getMethodDescriptor('tagger.tag');

// Provider access
$registry->getInterfaces();              // ['tagger', 'taggerAdmin', ...]
$registry->getProviderForInterface('tagger');
```

### DI wiring

`ApiRegistry` is bound as a factory in `DefaultInjectorBindings`:

```php
ApiRegistry::class => ApiRegistryFactory::class,
```

`ApiRegistryFactory` auto-discovers providers at construction time:

1. Load the list of installed apps from `RegistryConfigLoader`
2. For each app, check if `Horde\{App}\Api` exists and implements
   `ApiInterfaceListProvider`
3. Call `getApiInterfaceList()` to get the `interface → providerClass` map
4. Instantiate each provider via the injector
5. Register providers that implement both `ApiProvider` and
   `MethodInvoker`

Apps that have no modern API or fail to instantiate are silently skipped.
The registry starts empty and fills up as providers are discovered.

### Declaring interfaces in an app

Create `src/Api.php` implementing `ApiInterfaceListProvider`:

```php
// src/Api.php
namespace Horde\Content;

use Horde\Content\Api\TaggerProvider;
use Horde\Content\Api\TaggerAdminProvider;
use Horde\Core\Api\ApiInterfaceListProvider;

class Api implements ApiInterfaceListProvider
{
    public function getApiInterfaceList(): array
    {
        return [
            'tagger' => TaggerProvider::class,
            'taggerAdmin' => TaggerAdminProvider::class,
        ];
    }
}
```

The class must follow the naming convention `Horde\{Ucfirst_app}\Api` so
the factory can find it by convention. Interface names must not contain
dots (the dot is reserved for `interface.method` separation).

### Writing a provider

A provider wraps an existing service and exposes it through the dispatch
interfaces:

```php
// src/Api/TaggerProvider.php
namespace Horde\Content\Api;

use Content_Tagger;
use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\ApiProvider;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde\Rpc\Dispatch\MethodInvoker;
use Horde\Rpc\Dispatch\Result;

class TaggerProvider implements ApiProvider, MethodInvoker
{
    private readonly array $descriptors;

    public function __construct(
        private readonly Content_Tagger $tagger,
    ) {
        $this->descriptors = $this->buildDescriptors();
    }

    public function hasMethod(string $method, ?ApiCallContext $context = null): bool
    {
        return isset($this->descriptors[$method]);
    }

    public function getMethodDescriptor(string $method, ?ApiCallContext $context = null): ?MethodDescriptor
    {
        return $this->descriptors[$method] ?? null;
    }

    public function listMethods(?ApiCallContext $context = null): array
    {
        return array_values($this->descriptors);
    }

    public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
    {
        return match ($method) {
            'tag' => new Result($this->tagger->tag($params[0], $params[1], $params[2])),
            // ... other methods ...
            default => throw new \RuntimeException("Unknown method \"$method\""),
        };
    }

    private function buildDescriptors(): array
    {
        return [
            'tag' => new MethodDescriptor(
                name: 'tag',
                description: 'Add tags to an object',
                parameters: [
                    ['name' => 'userId', 'type' => 'mixed', 'required' => true],
                    ['name' => 'objectId', 'type' => 'mixed', 'required' => true],
                    ['name' => 'tags', 'type' => 'array', 'required' => true],
                ],
                returnType: 'void',
            ),
            // ... other descriptors ...
        ];
    }
}
```

### Permission-gated providers

Providers can use `ApiCallContext` to restrict visibility and access.
`TaggerAdminProvider` demonstrates this pattern:

```php
private function isAdmin(?ApiCallContext $context): bool
{
    $perms = $context?->getAttribute('permissions', []);
    return is_array($perms) && in_array('admin', $perms, true);
}

public function listMethods(?ApiCallContext $context = null): array
{
    if (!$this->isAdmin($context)) {
        return [];
    }
    return array_values($this->descriptors);
}
```

Methods are invisible without the required context. The `permissions` array
on `MethodDescriptor` declares what a method requires. Transports and
admin screens can display this information.

## RPC transports

### JSON-RPC 2.0 (modern)

The `horde/Rpc` package provides a complete JSON-RPC 2.0 stack:

```
HTTP Request
 - JsonRpcHandler (PSR-15 middleware)
    - Codec (decode JSON-RPC envelope)
      - Dispatcher (route to provider/invoker)
        - ApiProvider.hasMethod()
        - MethodInvoker.invoke()
      - Codec (encode JSON-RPC response)
 - HTTP Response
```

`JsonRpcHandler` wires the components together:

```php
$handler = new JsonRpcHandler(
    provider: $apiRegistry,       // ApiRegistry as the single provider
    invoker: $apiRegistry,        // ApiRegistry as the single invoker
    responseFactory: $responseFactory,
    streamFactory: $streamFactory,
    eventDispatcher: $eventDispatcher,
);

// Use as PSR-15 handler or middleware
$middleware = $handler->getMiddleware();
```

The `Dispatcher` checks the provider first, then falls back to built-in
system methods:

| Method | Response |
|--------|----------|
| `rpc.discover` | Lists all available methods with metadata |
| `rpc.ping` | Returns `"pong"` |

### Legacy bridge (HordeRegistryApiProvider)

For installations migrating from `Horde_Rpc_Jsonrpc`, the
`HordeRegistryApiProvider` bridges `Horde_Registry` into the modern
dispatch interfaces:

```php
$provider = new HordeRegistryApiProvider($registry);
$dispatcher = new Dispatcher($provider, $provider);
```

It translates between dot notation (`tagger.tag`) and the slash notation
(`tagger/tag`) that `Horde_Registry` expects. This allows legacy and modern
methods to be served through the same JSON-RPC endpoint.

### XML-RPC and SOAP (currently not available as modern APIs)

The legacy `Horde_Rpc_Xmlrpc` and `Horde_Rpc_Soap` transports operate
through `Horde_Registry` directly. They convert between dot notation on the
wire and slash notation internally. These transports do not use the modern
dispatch interfaces.

## Coexistence

Both systems run independently in the same installation:

- Modern APIs are registered via `Horde\{App}\Api` classes and dispatched
  through `ApiRegistry`
- Legacy APIs have their methods directly on the Horde_{App}_Api class
- Both aggregators depend on the registry to make the final decision which of the available APIs to actually expose
- An app can provide both: a legacy `Horde_Registry_Api` in `lib/Api.php`
  and modern providers in `src/Api/`
- The admin screen at `/admin/apis/` shows both systems side by side

The modern system does not replace or wrap the legacy system. They are
parallel paths. Migration happens per-interface: an app adds a modern
provider alongside its legacy API, then consumers switch at their own pace.

## AJAX transport

The Horde Dynamic UI communicates with the server through an AJAX layer
built on `HordeCore.js`. This transport is distinct from JSON-RPC — it uses
its own request/response envelope and its own URL conventions.

### URL forms

**Two-form (legacy):** `/services/ajax.php/{app}/{action}`

The original URL pattern. Session auth only. The controller creates a
legacy `Horde_Core_Ajax_Application` via factory, calls `doAction()`, and
wraps the result in the HordeCore envelope. Existing apps continue to use
this form unchanged.

**Three-form (modern):** `/services/ajax.php/{app}/ajax/{interface.method}`

New URL pattern for direct `ApiRegistry` dispatch. Supports dual auth
(JWT + session). The controller bypasses the legacy Application entirely:
it builds an `ApiCallContext` from request attributes, calls
`$apiRegistry->invoke($qualifiedMethod, $params, $context)`, and wraps
`Result->value` in the envelope.

The three-form path is intended for new providers that want direct
dispatch without legacy handler registration. Nothing calls it yet in
production — it is additive infrastructure.

### Response envelope

Both forms return the same JSON envelope, wrapped in an XSSI prevention
prefix:

```
/*-secure-{"response": ..., "msgs": [...], "tasks": {...}}*/
```

| Field | Content |
|-------|---------|
| `response` | Action return value (any JSON-serializable data) |
| `msgs` | Notification messages drained from the handler stack |
| `tasks` | Unsolicited task data keyed by `app:taskname` |

The `HordeCoreEnvelopeBuilder` (in `horde/Core`) produces this envelope
as a PSR-7 response. It is a pure data transformer with no globals.

When `jsonhtml=1` is passed, the response body is HTML-entity-encoded
(legacy pattern for iframe-based uploads in older browsers).

### Authentication and CSRF

The two-form route uses `AuthHordeSession` middleware only — matching the
behaviour of the `services/ajax.php` script. CSRF token validation happens
inside the `Horde_Core_Ajax_Application` constructor via
`$session->checkToken()`.

The three-form route uses a layered middleware stack:

1. `JwtAuthMiddleware` — sets `auth_type=jwt` and `jwt_user_id` on the
   request if a valid Bearer token is present
2. `AuthHordeSession` — sets `HORDE_AUTHENTICATED_USER` from session
3. `DemandAuthenticatedUser` — rejects if neither auth succeeded
4. `ConditionalCsrfMiddleware` — skips CSRF for JWT (Bearer tokens are
   not auto-sent by browsers), enforces for session auth

This dual-auth model lets the same endpoint serve both traditional
browser sessions and headless JWT clients.

### Action resolution order (doAction)

When the two-form path dispatches through `doAction()`, the resolution
order is:

1. **Legacy handlers** — registered via `addHandler()` in the app's
   `_init()`. First handler that `has($action)` wins.
2. **ApiRegistry fallback** — if the app's `Horde\{App}\Api` class
   implements `ApiInterfaceListProvider`, iterate its declared interfaces
   looking for one that exposes the action. First match invokes via
   `$apiRegistry->invoke()`.
3. **Application hooks** — `ajaxaction_handle` hook, then deprecated
   `ajaxaction` hook.
4. **Error** — throws if nothing handles the action.

The ApiRegistry fallback (step 2) is additive: legacy handlers always win
in resolution order. The fallback only activates for actions that would
otherwise reach the hooks or error out. This allows providers to be
gradually introduced alongside existing handlers without changing
behaviour.

The fallback is wrapped in try/catch — if the `ApiRegistry` is not wired
(e.g. in a minimal installation), dispatch silently skips to hooks.

### Migrating a handler action to a provider

To move an action from a legacy handler to a modern provider:

1. Create a provider class implementing `ApiProvider` and
   `MethodInvoker` (see "Writing a provider" above)
2. Register the interface in the app's `src/Api.php`
3. Remove the method from the legacy handler
4. The action now resolves through the ApiRegistry fallback (two-form) or
   direct dispatch (three-form)

Since the handler is checked first, adding a provider alongside an
existing handler is safe — the handler continues to win until removed.

### Provider parameters from AJAX

When invoked through the two-form fallback, providers receive parameters
as a flat associative array derived from `iterator_to_array($vars)`. This
matches the form data that HordeCore.js sends.

When invoked through the three-form path, providers receive parameters
merged from query string and parsed request body — same flat array, but
built from the PSR-7 request directly.

Providers should accept named parameters via the `$params` array rather
than positional arguments when supporting the AJAX transport.

## Package boundaries

```
horde/Rpc          Interfaces, value objects, JSON-RPC transport
                   No dependency on Horde_Registry or horde/Core

horde/Core         ApiRegistry, ApiRegistryFactory, DI bindings
                   Depends on horde/Rpc for interfaces

App packages       Provider implementations, Api class
                   Depend on horde/Rpc for interfaces
                   Autoloaded by horde/Core factory via convention
```

`horde/Rpc` is deliberately independent of `horde/Core`. This means
providers and transports can be tested without bootstrapping the full Horde
environment.
