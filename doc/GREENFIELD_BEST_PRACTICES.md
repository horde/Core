# Greenfield Best Practices

Guidelines for writing new code in the Horde 6 Core ecosystem. These apply to
new applications, new features in existing applications and new packages close to the core.

For regular/standalone libraries, better follow general coding standards (PER-1) and decoupling practices.

For migrating existing code, better see `UPGRADING.md`. Upgrades follow different
constraints and needs.

## Architectural choices

### Routes over endpoints

Register PSR-15 routes in `config/routes.php` rather than creating
standalone PHP scripts in `services/` or top-level directories.

```php
// Good: route declaration
$mapper->buildRoute(uri: '/calendar/event/:id', name: 'EventView')
    ->withController(Event\ViewController::class)
    ->withMiddleware([AuthHordeSession::class, DemandAuthenticatedUser::class])
    ->withMethods(['GET'])
    ->add();

// Avoid: standalone script
// services/event.php with manual auth checks and output buffering
```

Routes give you middleware stacking, named URL generation, method
filtering, and consistent auth handling without procedural boilerplate.

### PSR-4 over PSR-0

All new code goes in `src/` with PSR-4 autoloading. This applies to
everything, be it controllers, providers, blocks, views, factories.

```
src/Block/WeatherBlock.php      -> Horde\MyApp\Block\WeatherBlock
src/Api/CalendarProvider.php    -> Horde\MyApp\Api\CalendarProvider
src/Factory/TaggerFactory.php   -> Horde\MyApp\Factory\TaggerFactory
```

Avoid creating new classes in `lib/` with underscore-separated naming.
The `lib/` tree is for legacy code that hasn't been migrated yet.
The exception may be code which is trying to assemble collections
by globs in filesystem or checking if a class exists by "guessing" the class name.

### Providers over handlers

For functionality that should be callable from multiple transports (AJAX, JSON-RPC, MCP, inter-app), best implement it as an `ApiProvider`
and `MethodInvoker` provider:

```php
class CalendarProvider implements ApiProvider, MethodInvoker
{
    public function __construct(
        private readonly CalendarRepository $calendars,
    ) {}

    public function invoke(string $method, array $params, ?ApiCallContext $context = null): Result
    {
        return match ($method) {
            'list' => new Result($this->calendars->listForUser($context->userId)),
            'create' => new Result($this->calendars->create($params['name'], $context->userId)),
            default => throw new RuntimeException("Unknown method \"$method\""),
        };
    }
}
```

Providers are automatically exposed through:
- Two-form AJAX (`/services/ajax.php/{app}/{action}`) via the doAction
  fallback
- Three-form AJAX (`/services/ajax.php/{app}/ajax/{interface.method}`)
  via direct dispatch
- JSON-RPC via `ApiRegistry`
- MCP tool listings via method descriptors
- Inter-app calls via `$apiRegistry->invoke()`

Write the logic once, expose it everywhere.

### Controllers for UI-specific workflows

Use standalone PSR-15 controllers when the action is inherently tied to
HTTP request/response semantics:

- Form submissions with redirects
- HTML page rendering
- File downloads / streaming responses
- OAuth flows with redirects
- Anything that returns HTML or triggers a browser navigation

```php
class EventCreateController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Parse form, validate, persist, redirect
    }
}
```

Controllers should be thin — delegate business logic to services or
repositories, not implement it inline.

### When NOT to use a provider

Providers are wrong for:
- Actions that return HTML (use a controller)
- Actions that need access to PSR-7 request/response objects (use a
  controller or middleware)
- One-off UI interactions that will never be called from another
  transport (use a controller — but consider whether that assumption will
  hold)

### Avoid legacy handler registration

Do not create new `Horde_Core_Ajax_Application_Handler` subclasses.
The handler architecture requires per-app registration in `_init()`,
ties actions to a specific application URL, and has no introspection
capability. Use providers instead.

## Dependency injection

### DI over globals

Never access `$GLOBALS['injector']`, `$GLOBALS['registry']`, or
`$GLOBALS['session']` in new code. Accept dependencies through
constructor injection:

```php
// Good
class EventService
{
    public function __construct(
        private readonly CalendarRepository $repo,
        private readonly Horde_Registry $registry,
    ) {}
}

// Avoid
class EventService
{
    public function doThing()
    {
        $repo = $GLOBALS['injector']->getInstance(CalendarRepository::class);
    }
}
```

### PSR-11 `get()` over `getInstance()`

`Horde_Injector` implements PSR-11's `ContainerInterface`. Prefer `get()`
over the legacy `getInstance()`:

```php
// Good
$service = $injector->get(CalendarService::class);

// Legacy (still works, but prefer the above)
$service = $injector->getInstance('CalendarService');
```

### Modern stateless factories over legacy factories

New factories should be simple, stateless classes with a `create()` method.
They receive their own dependencies through DI:

```php
class TaggerFactory
{
    public function __construct(
        private readonly Horde_Db_Adapter $db,
    ) {}

    public function create(): Content_Tagger
    {
        return new Content_Tagger($this->db);
    }
}
```

Avoid the legacy `Horde_Core_Factory_Base` pattern that pulls dependencies
from `$GLOBALS['injector']` at call time. Avoid closures as factory
bindings — they cannot be cached by OPcache and prevent preloading.

```php
// Good: named factory class (OPcache-friendly)
$injector->bindFactory(Content_Tagger::class, TaggerFactory::class, 'create');

// Avoid: closure binding (not cacheable)
$injector->bind(Content_Tagger::class, function ($injector) {
    return new Content_Tagger($injector->get(Horde_Db_Adapter::class));
});
```

## Type system

### Type hints against interfaces

Depend on interfaces, not concrete classes or abstract base classes:

```php
// Good: interface dependency
public function __construct(
    private readonly ApiProvider $provider,
    private readonly LoggerInterface $logger,
) {}

// Avoid: concrete class dependency
public function __construct(
    private readonly TaggerProvider $provider,
    private readonly Horde_Log_Logger $logger,
) {}
```

This applies to type hints in method signatures, constructor parameters,
and property declarations. It enables testing with mocks and allows
swapping implementations without changing consumers.

### Strict types everywhere

Every new file starts with `declare(strict_types=1)`. No exceptions.

## Banned in new code

The `Horde` and `Horde\Core\Horde` utility classes are legacy grab-bags of
static helpers. Do not call any method on them in new code:

- `Horde::url()` — use `Horde\Core\Uri\UriBuilder`
- `Horde::selfUrl()` — use `$request->getUri()`
- `Horde::redirect()` — return a 302 response from your controller
- `Horde::permissionDeniedError()` — throw or return a 403 response
- `Horde::getTempDir()`, `Horde::getTempFile()` — use `PathBuilder::withTmpDir()`
- Any other `Horde::*` or `Horde\Core\Horde::*` call

These classes exist solely for legacy code that hasn't been migrated.
Every method they provide has a modern equivalent that doesn't rely on
global state.

## Modern replacements

### Horde\Db over Horde_Db

Use `Horde\Db` (the namespaced package) for all new database work:

```php
use Horde\Db\Adapter;
use Horde\Db\Sql\Builder;

class EventRepository
{
    public function __construct(
        private readonly Adapter $db,
    ) {}
}
```

### SQL Builder over raw SQL

Use the SQL Builder for query construction rather than string
concatenation or low-level adapter calls:

```php
// Good: SQL Builder
$query = $this->db->select()
    ->from('kronolith_events')
    ->where('calendar_id = ?', $calendarId)
    ->orderBy('event_start');

// Avoid: raw SQL strings
$sql = "SELECT * FROM kronolith_events WHERE calendar_id = "
     . $this->db->quoteString($calendarId)
     . " ORDER BY event_start";
```

The builder handles quoting, escaping, and dialect differences. It
produces readable code that's resistant to SQL injection.

### Rdo domain models over Driver architecture

For new domain models, use the Rdo (Rampage Data Objects) pattern with
repositories:

```php
class Event extends Horde_Rdo_Base
{
    // Domain model with typed properties
}

class EventMapper extends Horde_Rdo_Mapper
{
    protected $_table = 'kronolith_events';
    // Relationships, field mappings
}
```

The "Driver" architecture (abstract base with SQL/Kolab/LDAP subclasses)
was appropriate when backends varied wildly. For new features where SQL
is the storage layer, Rdo gives you a proper domain model with
relationships, lazy loading, and query building.

### Horde\Util\Variables over Horde_Variables

For request data handling in non-PSR-7 contexts (legacy handler support):

```php
use Horde\Util\Variables;

// Signature-compatible with Horde_Variables but in the modern namespace
$vars = new Variables($requestData);
```

### PSR-7/15 over legacy request/response

For anything touching HTTP, use PSR-7 messages and PSR-15 handlers:

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

// Good: PSR-15 handler
class MyController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        // ...
    }
}

// Avoid: legacy patterns
Horde::url('mypage.php')->add('id', $id)->redirect();
$request = new Horde_Controller_Request_Http();
```

For URL generation, use `Horde\Core\Path\PathBuilder` for filesystem
paths and `Horde\Http\Uri` for application URLs. These are superior to
handling the route mapper directly — they handle webroot resolution,
path normalization, and traversal protection:

```php
// Filesystem path (via DI)
$path = $this->pathBuilder->withAppFileroot('kronolith')
    ->withSlug('templates')
    ->withPart('event/edit.html.php');

// Application URL
$uri = new Uri($registry->get('webroot', 'kronolith') . '/event/' . $id);
$uri = $uri->withQuery(http_build_query(['action' => 'edit']));
```

Never use `Horde::url()`, `Horde_Url`, or `Horde\Core\Horde` in new code.

## Application structure

### Horde_Registry_Application

New applications still need a `lib/Application.php` extending
`Horde_Registry_Application` for integration with the Horde framework
(sidebar, topbar, preferences, permissions). Keep this file minimal —
it's glue code, not a place for business logic.

Implement the required hooks (`$version`, `appInitFailure()`, etc.) but
delegate everything else to services and controllers.

### Horde_Registry_Api (legacy inter-app)

If your app needs to expose methods to legacy callers that still use
`$registry->call('interface/method')`, provide a thin
`lib/Api.php` extending `Horde_Registry_Api`. These methods should
delegate to the same services that your modern providers use:

```php
class MyApp_Api extends Horde_Registry_Api
{
    public function listItems()
    {
        // Delegate to the service that the provider also uses
        return $GLOBALS['injector']->get(ItemService::class)->listAll();
    }
}
```

For new inter-app communication, prefer calling through `ApiRegistry`
directly. The legacy bridge exists for backwards compatibility with
apps that haven't migrated yet.

### Blocks

Portal blocks go in `src/Block/` with PSR-4 naming:

```php
namespace Horde\MyApp\Block;

class RecentItems extends Horde_Core_Block
{
    // Block implementation
}
```

Register them in the app's `config/Block/` directory. The block framework
still expects `Horde_Core_Block` subclasses, but the class itself should
live in `src/` with proper namespacing.

## Testing

### Providers are unit-testable

Providers have no HTTP dependency. Test them directly:

```php
$provider = new CalendarProvider(new InMemoryCalendarRepository());
$result = $provider->invoke('list', [], new ApiCallContext(['userId' => 'testuser']));
self::assertCount(3, $result->value);
```

### Controllers need integration tests

Controllers interact with HTTP. Test them with PSR-7 request factories
or through the router in integration tests. Use `phpunit.xml.dist` test
suites to separate unit from integration tests.

### No mocking the database

Integration tests that touch persistence should use a real database
connection. Mock/stub boundaries should be at the service/repository
layer, not the database adapter layer.

## Anti-patterns

| Pattern | Problem | Alternative |
|---------|---------|-------------|
| Business logic in controllers | Untestable, not reusable across transports | Extract to services/providers |
| Provider accepting `ServerRequestInterface` | Couples to HTTP, breaks JSON-RPC/MCP | Accept `array $params` + `ApiCallContext` |
| Closure factory bindings | Not OPcache-cacheable | Named factory classes |
| `Horde::url()` in new code | Global state, not PSR-7 | `Horde\Http\Uri` or `Horde\Core\Path\PathBuilder` |
| Abstract "Driver" with one implementation | Premature abstraction | Rdo mapper or direct repository |
| Handler subclass for new action | Per-app registration, no introspection | Provider |
| `global $injector` in a service | Hidden dependency, untestable | Constructor injection |
| Type hint on concrete class | Prevents substitution and testing | Interface type hint |
| Raw SQL string concatenation | Injection risk, dialect-dependent | SQL Builder |
