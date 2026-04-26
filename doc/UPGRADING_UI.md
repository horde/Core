# Upgrading UI from Horde_Controller and Page Scripts to Modern Controllers

This guide describes how to replace legacy entrypoint page scripts with modern PSR-15 controllers, route-based dispatch and the new service/builder APIs in `horde/Core`. Page scripts mix logic with presentation and rely on globals like `$conf`,
`$prefs`, `Horde::url()` and `Horde_Page_Output`.

---

## Table of Contents

1. [Overview: Old vs New](#1-overview-old-vs-new)
2. [Setting Up a PSR-15 Controller](#2-setting-up-a-psr-15-controller)
3. [Defining Routes](#3-defining-routes)
4. [Replacing registry->get() and Horde:: Calls with UriBuilder and PathBuilder](#4-replacing-registryget-and-horde-calls-with-uribuilder-and-pathbuilder)
5. [Signing URLs the H6 Way](#5-signing-urls-the-modern-way)
6. [Access Keys the H6 Way](#6-access-keys-the-modern-way)
7. [Replacing Horde_Perms with PermissionService](#7-replacing-horde_perms-with-permissionservice)
8. [Replacing Horde_Prefs / $prefs with PrefsService](#8-replacing-horde_prefs--prefs-with-prefsservice)
9. [Replacing the $conf Global with ConfigLoader](#9-replacing-the-conf-global-with-configloader)
10. [Assets: Replacing Horde_Page_Output](#10-assets-replacing-horde_page_output)
11. [Middleware Stack](#11-middleware-stack)
12. [Complete Migration Example](#12-complete-migration-example)

---

## 1. Overview: Old vs New

### Legacy entrypoint pattern

A typical legacy page script (`list.php`, `view.php`, etc.) does
everything in a single file:

```php
<?php
// 1. Bootstrap
require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('nag');

// 2. Globals everywhere
global $conf, $prefs, $registry, $injector, $notification, $page_output;

// 3. Logic mixed with presentation
$page_output->addScriptFile('tables.js', 'horde');
$page_output->addStylesheet('list.css');
$page_output->header(['title' => _("Task List")]);

// 4. Direct output
echo '<div>...';

$page_output->footer();
```

### Modern controller pattern

| Concern | Legacy | Modern |
|---------|--------|--------|
| Entry point | Physical `.php` file | Route in `config/routes.php` |
| Request handling | Global `$_GET`, `$_POST` | `ServerRequestInterface` |
| Response | Direct `echo` / `Horde_Page_Output` | `ResponseInterface` |
| URL building | `Horde::url()`, `$registry->get()` | `UriBuilder` |
| File paths | `$registry->get('fileroot')`, constants | `PathBuilder` |
| Configuration | `global $conf` | `ConfigLoader` -> `State` |
| Preferences | `global $prefs` | `PrefsService` |
| Permissions | `Horde_Perms` bitmasks | `PermissionService` |
| Assets | `$page_output->addScriptFile()` | `PageComposer` / `AssetCollector` |
| URL signing | Token in `Horde_Url` | `UrlSigner` (HMAC) |
| Access keys | `Horde::getAccessKey()` | `AccessKeyTracker` |
| Auth checks | `Horde_Auth::isAuthenticated()` | Middleware: `AuthHordeSession`, `DemandAuthenticatedUser` |

---

## 2. Setting Up a PSR-15 Controller

A controller is a class that implements `Psr\Http\Server\RequestHandlerInterface`.
Dependencies are injected via the constructor.  The controller receives
a PSR-7 `ServerRequestInterface` and returns a `ResponseInterface`.

### Minimal controller

```php
<?php

declare(strict_types=1);

namespace Horde\Nag\Controller;

use Horde\Core\Service\PermissionService;
use Horde\Core\Service\PrefsService;
use Horde\Core\Uri\UriBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TaskListController implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly PermissionService $permissions,
        private readonly PrefsService $prefsService,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('HORDE_AUTHENTICATED_USER');

        if (!$this->permissions->hasPermission('nag:tasklists', $user, ['read'])) {
            return $this->responseFactory->createResponse(403);
        }

        $defaultList = $this->prefsService->getValue($user, 'nag', 'default_tasklist');

        // Preferred: use a named route defined in config/routes.php
        $editUrl = (string) $this->uriBuilder
            ->withNamedRoute('nag', 'TaskEdit', ['action' => 'new']);

        // Alternative: build manually when no named route exists
        $editUrl = (string) $this->uriBuilder
            ->withAppWebroot('nag')
            ->withPart('task')
            ->withPart('edit')
            ->withQueryParams(['action' => 'new']);

        // Build HTML response (your app composes its own page output)
        $html = $this->renderPage($defaultList, $editUrl);

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($this->streamFactory->createStream($html));
    }
}
```

Key points:

- **No globals.** Every dependency is constructor-injected.
- **No `Horde_Registry` for trivial Path/Uri handling.** The controller uses `UriBuilder`,
  `PermissionService` and `PrefsService` instead of the legacy registry, `Horde_Perms` and `$prefs`.
- **No `Horde_Page_Output`** The controller composes its own
  response body using `PageComposer` / templates / whatever fits the
  rendering mode.

### Request attributes set by middleware

The middleware stack populates request attributes your controller can
read:

```php
$user     = $request->getAttribute('HORDE_AUTHENTICATED_USER'); // string|null
$isGuest  = $request->getAttribute('HORDE_GUEST');              // bool
$registry = $request->getAttribute('registry');                 // Horde_Registry
$route    = $request->getAttribute('route');                    // array of matched route params
$match    = $request->getAttribute('matchResult');              // MatchResult object
```

### Route parameters

Route parameters from the URI pattern (e.g. `:id` in `/task/:id`)
are available in the `route` attribute:

```php
$route  = $request->getAttribute('route', []);
$taskId = $route['id'] ?? null;

// Query and POST params via PSR-7:
$page  = $request->getQueryParams()['page'] ?? '1';
$body  = $request->getParsedBody();
$title = $body['title'] ?? '';
```

### JSON / API controllers

```php
class TaskApiController implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $data = ['tasks' => [...]];
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withBody($this->streamFactory->createStream($json));
    }
}
```

---

## 3. Defining Routes

Routes live in `config/routes.php` inside each application.
The file receives a `$mapper` variable (a `Horde\Routes\Mapper` instance).
All routes are relative to app webroot (i.e. /nag/)

### Basic route

```php
$mapper->buildRoute(uri: '/tasks/', name: 'TaskList')
    ->withController(Responsive\TaskListController::class)
    ->withDefaults(['HordeAuthType' => 'authenticate'])
    ->add();
```

### Route with parameters

```php
$mapper->buildRoute(uri: '/task/:id', name: 'TaskView')
    ->withController(Responsive\TaskViewController::class)
    ->withDefaults(['HordeAuthType' => 'authenticate'])
    ->requires('id', '\d+')
    ->add();
```

### HTTP method restriction

```php
$mapper->buildRoute(uri: '/task/:id', name: 'TaskUpdate')
    ->withController(Responsive\TaskUpdateController::class)
    ->withMethods(['POST', 'PUT'])
    ->add();

// Shorthand for single methods
$mapper->buildRoute(uri: '/task/', name: 'TaskCreate')
    ->withController(Responsive\TaskCreateController::class)
    ->post()
    ->add();
```

### Middleware stack on a route

```php
$mapper->buildRoute(uri: '/admin/tasks/', name: 'AdminTaskList')
    ->withController(Admin\TaskAdminController::class)
    ->withMiddleware([
        \Horde\Core\Middleware\AuthHordeSession::class,
        \Horde\Core\Middleware\DemandAuthenticatedUser::class,
        \Horde\Core\Middleware\AuthIsGlobalAdmin::class,
    ])
    ->add();
```

### Legacy URL compatibility with secondary routes

When moving `/list.php` to `/tasks/`, keep the old URL working:

```php
$mapper->buildRoute(uri: '/tasks/', name: 'TaskList')
    ->withController(Responsive\TaskListController::class)
    ->withSecondaryRoute('/list.php')
    ->add();
```

Secondary routes match incoming requests but are never generated by
`withNamedRoute()`.

### Authentication types in route defaults

| `HordeAuthType` value | Meaning |
|------------------------|---------|
| `'authenticate'` | Require authenticated session (default stack) |
| `'NONE'` | No authentication required (public endpoint) |

---

## 4. Replacing registry->get() and Horde:: Calls with UriBuilder and PathBuilder

### UriBuilder for URLs (what the browser sees)

`Horde\Core\Uri\UriBuilder` extends `Horde\Http\Uri` (PSR-7 compliant)
and adds Horde-aware builder methods.  It is **immutable.** Every
`with*()` call returns a new instance.

**Constructor:**

```php
use Horde\Core\Uri\UriBuilder;
use Horde\Core\Config\RegistryState;

$uri = new UriBuilder(
    $registryState,     // RegistryState: immutable app definitions
    $routeProvider,     // RouteMapperProvider: for named route generation
    $request,           // ServerRequestInterface: scheme + authority from current request
);
```

**Replacing common patterns:**

```php
// OLD: $registry->get('webroot', 'nag')
// NEW:
$uri = $uriBuilder->withAppWebroot('nag');
echo $uri; // e.g. "/nag"

// OLD: $registry->get('themesuri', 'nag')
// NEW:
$uri = $uriBuilder->withThemesUri('nag');

// OLD: $registry->get('jsuri', 'nag')
// NEW:
$uri = $uriBuilder->withJsUri('nag');

// OLD: Horde::url('task/view') with Horde_Url chaining
// NEW:
$uri = $uriBuilder
    ->withAppWebroot('nag')
    ->withPart('task')
    ->withPart('view')
    ->withQueryParams(['id' => 42]);
echo $uri; // "https://example.com/nag/task/view?id=42"

// OLD: Horde_Script static URL building
// NEW:
$uri = $uriBuilder->withStaticUri();
echo $uri; // "https://example.com/horde/static"
```

**Generating URLs from named routes:**

```php
$uri = $uriBuilder->withNamedRoute('nag', 'TaskView', ['id' => 42]);
// Generates: /nag/task/42 (based on route definition)
```

**Appending path segments:**

- `withSlug('segment')` appends `/segment/` (with trailing slash)
- `withPart('segment')` appends `/segment` (no trailing slash)

**Query parameters:**

- `withQueryParams(['key' => 'value'])` sets query string from array
- `withQuery('key=value')` sets raw query string (PSR-7 standard)

**Bridging to Horde\Url\Url (for templates that still expect it):**

```php
$hordeUrl = $uriBuilder->withAppWebroot('nag')->toHordeUrl();
// Returns Horde\Url\Url object
```

### PathBuilder for filesystem paths (server-side)

`Horde\Core\Path\PathBuilder` builds filesystem paths with
lexical normalization that prevents `..` directory traversal escapes.

```php
use Horde\Core\Path\PathBuilder;

$path = new PathBuilder($registryState);

// OLD: $registry->get('fileroot', 'nag')
// NEW:
$appRoot = $path->withAppFileroot('nag');
echo $appRoot; // "/srv/www/horde/nag"

// OLD: $registry->get('fileroot', 'nag') . '/themes'
// NEW:
$themes = $path->withAppThemesDir('nag');

// OLD: $registry->get('fileroot', 'nag') . '/js'
// NEW:
$jsDir = $path->withAppJsDir('nag');

// Build deeper paths:
$templateFile = $path
    ->withAppFileroot('nag')
    ->withPart('templates')
    ->withPart('responsive')
    ->withPart('list.html.php');
echo $templateFile; // "/srv/www/horde/nag/templates/responsive/list.html.php"

// Convert to SplFileInfo:
$fileInfo = $templateFile->toSplFileInfo();
if ($fileInfo->isReadable()) { ... }

// Config directory:
$confDir = $path->withConfigDir('nag');
// "/srv/www/horde/var/config/nag"

// Temp directory:
$tmpDir = $path->withTmpDir();
// "/srv/www/horde/var/tmp"
```

PathBuilder prevents path traversal attacks.  If an appended segment
tries to escape the base directory, a `PathNormalizationException` is
thrown:

```php
// THROWS PathNormalizationException:
$path->withAppFileroot('nag')->withPart('../../etc/passwd');
```

---

## 5. Signing URLs the H6 Way

### Old pattern

Legacy code used `Horde_Url->add('token', ...)` with `Horde::getRequestToken()`
and `Horde::checkRequestToken()`.

### New pattern: UrlSigner

`Horde\Core\Assets\UrlSigner` is an interface with two implementations:

- **`HmacUrlSigner`** Signing turned on: appends `_t` (timestamp) and `_h`
  (HMAC-SHA1) query parameters
- **`NullUrlSigner`** Signing turned off in config: pass-through, no signing

**Signing a URL:**

```php
use Horde\Core\Assets\UrlSigner;

class MyController implements RequestHandlerInterface
{
    public function __construct(
        private readonly UrlSigner $signer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Example Code - in real controllers prefer UriBuilder
        $url = '/nag/task/delete?id=42';
        $signedUrl = $this->signer->signUrl($url);
        // → /nag/task/delete?id=42&_t=1714100000&_h=abc123...
    }
}
```

**Verifying a signed URL:**

```php
$originalUrl = $this->signer->verifySignedUrl($incomingUrl);
if ($originalUrl === false) {
    // Signature invalid or expired (default lifetime: 30 minutes)
    return $this->responseFactory->createResponse(403);
}
// $originalUrl is the URL without _t and _h params
```

**Signing/verifying query strings only:**

```php
$signed = $this->signer->signQueryString('id=42&action=delete');
$valid  = $this->signer->verifySignedQueryString($signedQueryString);
```

The `HmacUrlSigner` uses URI-safe base64 encoding (no `+`, `/`, `=`
characters) and a configurable lifetime in minutes.

---

## 6. Access Keys the H6 Way

### Old pattern

```php
// Legacy
$ak = Horde::getAccessKey(_("_Edit"));
$label = Horde::highlightAccessKey(_("_Edit"), $ak);
echo '<a accesskey="' . $ak . '">' . $label . '</a>';
```

### New pattern: AccessKeyTracker

`Horde\Core\View\AccessKeyTracker` tracks per-page key uniqueness
and handles multibyte/CJK locales correctly.

**Labels mark the preferred key with an underscore:** `_Edit` means
the key is `E`.

```php
use Horde\Core\View\AccessKeyTracker;

$tracker = new AccessKeyTracker(
    accessKeysEnabled: true,
    multibyte: false,  // true for CJK locales
);

// Get HTML attributes for an element:
$attrs = $tracker->getAccessKeyAndTitle('_Edit');
// → ['title' => 'Edit (Accesskey E)', 'accesskey' => 'e']

// Highlight the key letter in the label:
$highlighted = $tracker->highlight('_Edit', $attrs['accesskey'] ?? '');
// → '<span class="accessKey">E</span>dit'

// Build element:
echo sprintf(
    '<a title="%s" accesskey="%s">%s</a>',
    htmlspecialchars($attrs['title']),
    htmlspecialchars($attrs['accesskey'] ?? ''),
    $highlighted,
);
```

**Key methods:**

| Method | Returns | Purpose |
|--------|---------|---------|
| `acquire($label)` | `string` | Extract and reserve key from `_X` label; empty if already used |
| `strip($label)` | `string` | Remove `_X` marker, keep letter |
| `highlight($label, $key)` | `string` | Wrap key letter in `<span class="accessKey">` |
| `getAccessKeyAndTitle($label)` | `array` | Combined: `['title' => ..., 'accesskey' => ...]` |
| `reset()` | `void` | Clear state for next page |

In multibyte locales, `highlight()` appends the key as `(E)` after
the label text instead of wrapping inline, because CJK characters
cannot serve as keyboard mnemonics.

The tracker is available via DI with the `#[Factory]` attribute and
`AccessKeyTrackerFactory`.

---

## 7. Replacing Horde_Perms with PermissionService

### Old pattern

```php
// Legacy: bitmask constants, static calls
$perms = $GLOBALS['injector']->getInstance('Horde_Perms');
$perm = $perms->getPermission('nag:tasklists:personal');
if ($perm->hasPermission($user, Horde_Perms::READ)) { ... }
```

### New pattern: PermissionService

`Horde\Core\Service\PermissionService` uses string-based permission
names (colon-separated hierarchy) and boolean flag arrays instead of
bitmasks.

**Inject via constructor:**

```php
use Horde\Core\Service\PermissionService;

class TaskListController implements RequestHandlerInterface
{
    public function __construct(
        private readonly PermissionService $permissions,
    ) {}
```

**Checking permissions:**

```php
// Does user have read+edit on this resource?
if ($this->permissions->hasPermission(
    'nag:tasklists:personal',
    $username,
    ['read', 'edit'],
)) {
    // allowed
}
```

**Getting expanded permission flags:**

```php
$flags = $this->permissions->getUserPermissions('nag:tasklists:personal', $username);
// → ['show' => true, 'read' => true, 'edit' => false, 'delete' => false]
```

**API reference:**

| Method | Returns | Purpose |
|--------|---------|---------|
| `hasPermission($name, $user, $required)` | `bool` | True if user has ALL required flags |
| `getUserPermissions($name, $user)` | `array` | Expanded flag map for user |
| `exists($name)` | `bool` | Check if permission node exists |
| `get($name)` | `array` | Full permission details |
| `create($name, $type, $data)` | `void` | Create permission ('matrix' or 'boolean') |
| `update($name, $data)` | `void` | Update permission data |
| `delete($name, $force)` | `void` | Delete; `$force` removes children |
| `listAll()` | `array` | Flat list of all permission names |
| `getTree()` | `array` | Hierarchical tree |
| `getParents($name)` | `array` | Parent permission names |

**Permission names follow hierarchical convention:**
`{app}:{resource-type}:{resource-id}`

Examples:
- `nag:tasklists:personal`
- `kronolith:calendars:shared_team`
- `imp:folder:INBOX`
- `horde:admin:configuration`

---

## 8. Replacing Horde_Prefs / $prefs with PrefsService

### Old pattern

```php
// Legacy: global $prefs with implicit user and app scope
global $prefs;
$value = $prefs->getValue('default_tasklist');
$prefs->setValue('default_tasklist', 'personal');
```

### New pattern: PrefsService

`Horde\Core\Service\PrefsService` requires explicit user ID and
application scope on every call.

**Inject via constructor:**

```php
use Horde\Core\Service\PrefsService;

class TaskListController implements RequestHandlerInterface
{
    public function __construct(
        private readonly PrefsService $prefsService,
    ) {}
```

**Reading/writing preferences:**

```php
// Read (explicit uid + scope + key)
$defaultList = $this->prefsService->getValue($uid, 'nag', 'default_tasklist');

// Write
$this->prefsService->setValue($uid, 'nag', 'default_tasklist', 'personal');

// Delete
$this->prefsService->deleteValue($uid, 'nag', 'default_tasklist');

// Check existence
if ($this->prefsService->exists($uid, 'nag', 'default_tasklist')) { ... }

// Get all prefs in scope
$allNagPrefs = $this->prefsService->getAllInScope($uid, 'nag');
```

**Key differences from legacy:**

| Legacy `$prefs` | `PrefsService` |
|-----------------|----------------|
| User implicit (from session) | `$uid` required on every call |
| App scope implicit (from `appInit`) | `$scope` required on every call |
| Returns empty string for missing keys | Returns `null` for missing keys |
| Side-effects via hooks | Storage/retrieval only |

**Available backends:**

- `SqlPrefsService` (wraps `Horde_Prefs_Sql`)
- `FilePrefsService` (file-based at `{dir}/{scope}/{uid}.prefs`)
- `LdapPrefsService`  LDAP
- `NullPrefsService` testing or minimal sites (no-op)

---

## 9. Replacing the $conf Global with ConfigLoader

### Old pattern

```php
// Legacy: populated by Horde_Registry::appInit()
global $conf;
$driver = $conf['storage']['driver'];
$dsn = $conf['sql']['phptype'];
```

### New pattern: ConfigLoader

`Horde\Core\Config\ConfigLoader` loads configuration files and returns
an immutable `State` object.  It does **not** populate globals.

**Inject via constructor:**

```php
use Horde\Core\Config\ConfigLoader;

class TaskListController implements RequestHandlerInterface
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
    ) {}
```

**Loading configuration:**

```php
// Load app config (equivalent to $conf from conf.php)
$config = $this->configLoader->load('nag');

// Access values (ArrayAccess interface)
$driver = $config['storage']['driver'];
$dsn = $config['sql']['phptype'];

// Load horde-wide config
$hordeConfig = $this->configLoader->load('horde');
$secretKey = $hordeConfig['secret_key'];
```

**Config file loading order** (each subsequent file merges into the
previous via `array_replace_recursive`):

1. `{configBase}/{app}/conf.php` main config
2. `{configBase}/{app}/conf.d/*.php` snippets (alphabetical order)
3. `{configBase}/{app}/conf.local.php` local overrides
4. `{configBase}/{app}/conf-{hostname}.php` virtual host overrides

Same order as before. Vhost configs are now always loaded and cannot be turned off in main config.
No more merging between active app's config and horde/base config.

**The returned `State` object:**

- Implements `ArrayAccess` so you can access it like an array
- Implements `IteratorAggregate` for foreach loop usage
- Immutable. No writes back to config files
- Cached per `(app, file)` tuple within a request

**Config with metadata** (for admin config editors):

```php
$config = $this->configLoader->load('nag', 'conf.php', withMetadata: true);
// Returns ConfigStateWithMetadata with type info and descriptions
```

---

## 10. Assets: Replacing Horde_Page_Output

### Old pattern

```php
// Legacy
$page_output->addScriptFile('tables.js', 'horde');
$page_output->addScriptFile('tasks.js');
$page_output->addStylesheet('list.css');
$page_output->header(['title' => _("Task List")]);
// ... page content ...
$page_output->footer();
```

### New pattern: PageComposer + AssetCollector

`PageComposer` replaces `Horde_Page_Output`.  It renders the HTML
document skeleton (doctype, `<head>`, deferred scripts, `</body>`) while
`AssetCollector` collects CSS, JS and meta tags.

```php
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\PageComposer;
use Horde\Core\PageOutput\PageMeta;

class TaskListController implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly AssetCollector $assetCollector,
        private readonly PageComposer $pageComposer,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Collect assets
        $this->assetCollector->addStylesheetFile('/themes/default/tasks.css');
        $this->assetCollector->addScriptFile('/js/tables.js');
        $this->assetCollector->addMetaTag('viewport', 'width=device-width, initial-scale=1');

        // Render page skeleton
        $meta = new PageMeta(
            language: 'en',
            title: _("Task List"),
            bodyClass: 'nag-tasks',
            deferScripts: true,
        );

        $head = $this->pageComposer->renderHead($meta);
        $foot = $this->pageComposer->renderFoot();

        // Compose response body
        $html = $head . $this->renderContent() . $foot;

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($this->streamFactory->createStream($html));
    }
}
```

### CssDiscoverer / JsDiscoverer

For controllers that need asset-cascade discovery (theme fallbacks,
app-specific overrides), use the discoverer APIs:

```php
use Horde\Core\Assets\CssDiscoverer;
use Horde\Core\Assets\CssDiscoveryRequest;
use Horde\Core\Assets\JsDiscoverer;

// CSS: discovers files through the theme cascade
$discoverer = new CssDiscoverer(...);
$request = new CssDiscoveryRequest(
    files: ['tasks.css'],
    app: 'nag',
    subView: 'dynamic',
);

$cssUrls = [];
foreach ($discoverer->discover($request) as $entry) {
    $cssUrls[] = $entry->uri;
}

// JS: resolve a single file from an app's js/ directory
$jsDiscoverer = new JsDiscoverer(...);
$uri = $jsDiscoverer->resolve('tables.js', 'horde');
```

### ResponsiveControllerTrait (responsive/mobile UI only)

`ResponsiveControllerTrait` is a convenience layer for the responsive
UI that replaces the legacy "smartmobile" view.  It wraps
`ResponsiveAssets`, `ResponsiveTopbar` and `ResponsiveTemplateView`
into a single `renderTemplate()` call.

**This trait is NOT the general pattern for replacing page scripts.**
Use it only when building controllers for the responsive UI:

```php
class ResponsiveTaskController implements RequestHandlerInterface
{
    use ResponsiveControllerTrait;

    protected function getAppName(): string { return _("Tasks"); }
    protected function getTemplateBasePath(): string { return NAG_TEMPLATES . '/responsive/'; }
    // ... trait handles asset cascade + topbar automatically
}
```

Dynamic-mode controllers (the ones replacing legacy page scripts)
should use `PageComposer` / `AssetCollector` directly as shown above.

---

## 11. Middleware Stack

Routes declare a middleware stack that runs before your controller.
Available middleware in `Horde\Core\Middleware`:

| Middleware | Purpose |
|------------|---------|
| `AuthHordeSession` | Load session, set `HORDE_AUTHENTICATED_USER` and `HORDE_GUEST` attributes |
| `DemandAuthenticatedUser` | Return 401/redirect if not authenticated |
| `RedirectToLogin` | Redirect unauthenticated users to login page |
| `AuthIsGlobalAdmin` | Set admin flag on request |
| `DemandGlobalAdmin` | Require admin role, return 403 otherwise |
| `AuthHasPermission` | Check specific permission |
| `DemandSessionToken` | CSRF token validation |
| `JwtAuthMiddleware` | Validate JWT Bearer token |
| `JwtSession` | JWT-based session management |
| `RenderingModeMiddleware` | Set rendering mode (responsive/desktop) |
| `ErrorFilter` | Error/exception handling |
| `H5Controller` | Wrap legacy `Horde_Controller` for backward compatibility |

**Execution order:** middleware runs top-to-bottom, controller last.
Responses pass back bottom-to-top.

```
Request → AuthHordeSession → DemandAuthenticatedUser → Controller
                                                           ↓
Response ← AuthHordeSession ← DemandAuthenticatedUser ← Response
```

---

## 12. Complete Migration Example

### Before: `nag/list.php`

```php
<?php
require_once __DIR__ . '/lib/Application.php';
Horde_Registry::appInit('nag');

global $conf, $prefs, $registry, $page_output;

if (!$GLOBALS['injector']->getInstance('Horde_Perms')
    ->hasPermission('nag:tasklists', $GLOBALS['registry']->getAuth(), Horde_Perms::READ)) {
    throw new Horde_Exception('Permission denied');
}

$defaultList = $prefs->getValue('default_tasklist');
$webroot = $registry->get('webroot', 'nag');
$editUrl = Horde::url($webroot . '/task/edit');

$page_output->addScriptFile('tables.js', 'horde');
$page_output->header(['title' => _("Task List")]);

echo '<a href="' . $editUrl->add('action', 'new') . '">' . _("New Task") . '</a>';

$page_output->footer();
```

### After: route + controller

**`config/routes.php`:**

```php
$mapper->buildRoute(uri: '/tasks/', name: 'TaskList')
    ->withController(Controller\TaskListController::class)
    ->withDefaults(['HordeAuthType' => 'authenticate'])
    ->withSecondaryRoute('/list.php')
    ->withMiddleware([
        \Horde\Core\Middleware\AuthHordeSession::class,
        \Horde\Core\Middleware\DemandAuthenticatedUser::class,
    ])
    ->add();
```

**`src/Controller/TaskListController.php`:**

```php
<?php

declare(strict_types=1);

namespace Horde\Nag\Controller;

use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\PageComposer;
use Horde\Core\PageOutput\PageMeta;
use Horde\Core\Service\PermissionService;
use Horde\Core\Service\PrefsService;
use Horde\Core\Uri\UriBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TaskListController implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly AssetCollector $assetCollector,
        private readonly PageComposer $pageComposer,
        private readonly PermissionService $permissions,
        private readonly PrefsService $prefsService,
        private readonly UriBuilder $uriBuilder,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('HORDE_AUTHENTICATED_USER');

        if (!$this->permissions->hasPermission('nag:tasklists', $user, ['read'])) {
            return $this->responseFactory->createResponse(403);
        }

        $defaultList = $this->prefsService->getValue($user, 'nag', 'default_tasklist');

        $editUrl = (string) $this->uriBuilder
            ->withNamedRoute('nag', 'TaskEdit', ['action' => 'new']);

        // Collect page assets
        $this->assetCollector->addScriptFile('/js/tables.js');
        $this->assetCollector->addStylesheetFile('/themes/default/tasks.css');

        // Render page skeleton
        $meta = new PageMeta(
            language: 'en',
            title: _("Task List"),
            bodyClass: 'nag-tasks',
            deferScripts: true,
        );

        $head = $this->pageComposer->renderHead($meta);
        $foot = $this->pageComposer->renderFoot();

        $content = '<a href="' . htmlspecialchars($editUrl) . '">'
            . _("New Task") . '</a>';

        $html = $head . $content . $foot;

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withBody($this->streamFactory->createStream($html));
    }
}
```

### What changed

1. **No globals.** `$conf`, `$prefs`, `$registry`, `$page_output` are
   gone.  Dependencies are injected via the constructor.
2. **No `Horde_Registry`.** The controller uses `UriBuilder`,
   `PermissionService` and `PrefsService` directly.  No registry
   object needed for these cases.
3. **No `Horde_Perms` bitmasks.** `PermissionService::hasPermission()`
   with string-based permission flags.
4. **No `Horde::url()` access.** Use `UriBuilder` with named routes or fluent
   path building + `withQueryParams()`.
5. **No `Horde_Page_Output`** `PageComposer` + `AssetCollector`
   render the HTML skeleton and collect CSS/JS assets.
6. **No exposed file in web dir.** The URL `/tasks/` is defined by a route and
   `/list.php` is kept as a secondary route for backward compatibility.
7. **Auth via middleware.** `AuthHordeSession` + `DemandAuthenticatedUser`
   run before the controller, so `$request->getAttribute('HORDE_AUTHENTICATED_USER')`
   is always set.

### Responsive UI variant

For the responsive/mobile UI (replacing the legacy "smartmobile" view),
`ResponsiveControllerTrait` provides a higher-level convenience layer
that handles the CSS cascade, responsive topbar and template rendering
automatically.  See [Section 10](#10-assets-replacing-horde_page_output)
for details.  The trait is **not** the general pattern for replacing
legacy page scripts -- it targets the responsive UI specifically.
