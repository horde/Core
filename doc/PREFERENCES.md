# Preferences System

This document describes the modern `PrefsService` API and how to migrate from
directly using the legacy `Horde_Prefs` / `Horde_Core_Factory_Prefs`.

It also documents the cascade of file-based defaults and user preferences.

---

## 1. Overview

Preferences in Horde are **scoped by user and application**.  Every pref
lives at the intersection of three coordinates:

```
(uid, scope, key)  ->  value
```

`uid` is the authenticating username string.  `scope` is the application name
(`'horde'`, `'imp'`, `'turba'` etc).  `key` is the preference name from an application's
`prefs.php` (`'theme'`, `'language'`, `'default_tasklist'`).

The Horde 6 entry point is the `Horde\Core\Service\PrefsService` interface.
Inject it where you need it. The DI container selects the correct backend
(SQL, LDAP, file etc) at runtime based on the horde base app's `conf.php`.

---

## 2. The PrefsService Interface

```php
namespace Horde\Core\Service;

interface PrefsService
{
    /** Returns stored or config-default value, null if completely absent. */
    public function getValue(string $uid, string $scope, string $key);

    /**
     * Write a value to the user storage layer.
     * Throws RuntimeException if the pref is locked in config.
     */
    public function setValue(string $uid, string $scope, string $key, $value): void;

    /** Remove the user-level row.  No-op if the row does not exist. */
    public function deleteValue(string $uid, string $scope, string $key): void;

    /**
     * Returns every effective value in a scope as key => value.
     * Config defaults appear even when no DB row exists; locked prefs
     * use the config value regardless of any stale DB row.
     */
    public function getAllInScope(string $uid, string $scope): array;

    /** True if the pref exists (config default or user row). */
    public function exists(string $uid, string $scope, string $key): bool;

    /**
     * True if the pref is locked by a config layer.
     * Lock state is deployment-wide, so $uid does not change the result.
     */
    public function isLocked(string $uid, string $scope, string $key): bool;
}
```

**Important difference from legacy `$prefs` global:**

| `$prefs` (legacy) | `PrefsService` |
|---|---|
| User from session, implicit | `$uid` explicit on every call |
| Scope from `appInit()`, implicit | `$scope` explicit on every call |
| Missing key → empty string `''` | Missing key → `null` |
| Hooks run automatically | Storage/retrieval only |
| Singleton, cached per request | Stateless. No per-call caching |

---

## 3. Available Backends

All backends implement `PrefsService`.  The right one is selected automatically
by `PrefsServiceFactory` based on `$conf['prefs']['driver']`.

| Class | Driver value | Notes |
|---|---|---|
| `SqlPrefsService` | `sql` | Stores in a `horde_prefs` sql table. Applies full 5-layer config cascade via `PrefsConfigLoader`. Default. |
| `FilePrefsService` | `file` | Serialised PHP arrays at `{dir}/{scope}/{uid}.prefs`. No config cascade, no locking or defaults. |
| `LdapPrefsService` | `ldap` | LDAP attributes using `hordePerson` objectClass.|
| `MongoPrefsService` | `nosql` | MongoDB documents. |
| `NullPrefsService` | `null`, `session` | In-memory only. Nothing persists. For testing or minimal sites. |

---

## 4. Factory Architecture

### 4.1 Root factory in `horde/core`

`Horde\Core\Factory\PrefsServiceFactory` (in `src/Factory/`) is the
authoritative factory for the H6 API.  It is registered in
`DefaultInjectorBindings`:

```php
PrefsService::class => PrefsServiceFactory::class,
```

When `$injector->get(PrefsService::class)` is called, the factory:

1. Loads `horde/conf.php` via `ConfigLoader`.
2. Reads `$conf['prefs']['driver']` and `$conf['prefs']['params']`.
3. Constructs the matching `*PrefsService` implementation.
4. For the SQL, LDAP and Mongo backends it also resolves `PrefsConfigLoader` from the container
   and passes it into `SqlPrefsService`.

```
Injector::get(PrefsService::class)
  └─ PrefsServiceFactory::create()
       ├─ ConfigLoader → reads conf.php → driver = 'sql'
       ├─ HordeDbService::getAdapter()
       ├─ PrefsConfigLoader  ← resolved from container via PrefsConfigLoaderFactory
       └─ new SqlPrefsService($db, $prefsConfigLoader, $table)
```

`PrefsConfigLoader` itself is registered as:

```php
PrefsConfigLoader::class => PrefsConfigLoaderFactory::class,
```

`PrefsConfigLoaderFactory` reads `HORDE_CONFIG_BASE` and `HORDE_BASE` to
locate each app's `prefs.php` files, mirrors the pattern of
`BackendConfigLoaderFactory`.

### 4.2 Legacy factory in `lib/` (`Horde_Core_Factory_Prefs`)

`Horde_Core_Factory_Prefs` (`lib/Horde/Core/Factory/Prefs.php`) is the
**pre-existing legacy factory**.  It creates `Horde_Prefs` instances
directly, wiring three drivers:

```
[Horde_Core_Prefs_Storage_Configuration, $userStorageDriver, Horde_Core_Prefs_Storage_Hooks]
```

Legacy code that calls `$GLOBALS['injector']->get('Horde_Core_Factory_Prefs')->create($scope)`
or accesses the `$prefs` global still goes through this factory.  It requires the legacy application bootstrap and consumes the globals `$GLOBALS['registry']` and `$GLOBALS['conf']`.

`Horde_Core_Prefs_Storage_Configuration` is the config layer in the legacy
stack. It has a guard for global registry:

```php
if (!$registry instanceof Horde_Registry) {
    return $scope_ob;   // no defaults, no locking
}
```

This makes it a silent no-op on any PSR-15 route that skips `appInit()`.
The modern `SqlPrefsService` uses `PrefsConfigLoaderStorage` and has no such dependency.

### 4.3 Coexistence during migration

Both factories can coexist in the same process.  A partially-migrated app may
have:

- Legacy templates and AJAX handlers using `$GLOBALS['prefs']` (legacy stack).
- New PSR-15 controllers injecting `PrefsService` (modern stack).

Both read from the same underlying `horde_prefs` table or LDAP resource.
Write through either path lands in the same rows.

The key behavioural difference is that only `SqlPrefsService` correctly
surfaces config defaults on PSR-15 routes.  If a new route reads a pref that
has a `'value'` in `prefs.php` but no user row, `PrefsService::getValue()`
returns the default; the legacy `$prefs->getValue()` on the same route returns
`null` (because its config driver is a no-op without `$GLOBALS['registry']`).

---

## 5. Loading Precedence / Stacking

Effective preference values are assembled in two phases: a **config file
cascade** followed by **user storage**.

### 5.1 Config file cascade (five layers)

`PrefsConfigLoader` merges `prefs.php` files in this order.  Each layer
overwrites keys set by earlier layers.

```
Layer 1  vendor/horde/{app}/config/prefs.php          ← package defaults
Layer 2  {HORDE_CONFIG_BASE}/{app}/prefs.php           ← deployment base
Layer 3  {HORDE_CONFIG_BASE}/{app}/prefs.d/*.php       ← snippets, alphabetical
Layer 4  {HORDE_CONFIG_BASE}/{app}/prefs.local.php     ← local override
Layer 5  {HORDE_CONFIG_BASE}/{app}/prefs-{hostname}.php ← vhost override
```

Snippets in `prefs.d/` are `sort()`ed before inclusion, guaranteeing
alphabetical (and therefore deterministic) merge order regardless of
filesystem.

The result is a `PrefsState` object: an immutable snapshot of every pref
definition (`'value'`, `'locked'`, `'type'`, `'hook'`, …) after all five
layers have been merged.

### 5.2 User storage layer (sixth layer)

`Horde_Prefs_Storage_Sql` applies user-specific values on top of the config
defaults.  Only **unlocked** prefs are overwritten; locked prefs keep their
config-layer value even if a DB row exists.

```
Config default from prefs.php cascade (layers 1-5)
     │
     │  if not locked: DB row overwrites default
     │
     └Effective value
```

The full driver stack inside `SqlPrefsService::createPrefs()` is:

```
[$configDriver,   $sqlDriver  ]
  │                │
  │ sets defaults  │ overwrites unlocked prefs
  │ sets locks     │ (reads horde_prefs table)
  └ Horde_Prefs applies them left-to-right ->  effective value
```

`$configDriver` is `PrefsConfigLoaderStorage`, which calls
`PrefsConfigLoader::load($scope)` and does not require `$GLOBALS['registry']`.

### 5.3 getAllInScope() stacking

`getAllInScope()` builds the same merged view without instantiating
`Horde_Prefs`:

1. Seed result from config defaults (`PrefsState::getAllPrefs()`).
   UI-only types (`link`, `prefslink`, `rawhtml`, `container`, `special`) and
   prefs without a `'value'` key are skipped.
2. Track locked keys in a separate set.
3. Run the SQL query for the given `(uid, scope)`.
4. For each DB row:
   - If the key is locked → skip (config default wins).
   - Otherwise → overwrite the config default with the DB value.
5. DB rows for keys not present in the config are included as-is (custom or
   deprecated prefs).

### 5.4 Lock semantics

A pref is locked when any config layer sets `'locked' => true`.  Locking is
**deployment-wide**. It applies to every user equally.  The `$uid` parameter
on `isLocked()` exists only to satisfy the interface contract. It has no
effect on the result.

Locked prefs:
- `getValue()` → returns the config-layer value, never the DB row.
- `getAllInScope()` → same; DB row silently ignored.
- `isLocked()` → `true`.
- `setValue()` → throws `RuntimeException`.
- `deleteValue()` → succeeds (removes the stale row) but the config value is
  restored on the next read.

---

## 6. Converting Legacy `$prefs` / `Horde_Prefs` References

### 6.1 Simple read/write in a controller

```php
// Before -> legacy global, implicit user and scope
global $prefs;
$theme = $prefs->getValue('theme');          // user from session
$prefs->setValue('theme', 'silver');         // scope from appInit()

// After -> injected service, explicit coordinates
use Horde\Core\Service\PrefsService;

class ThemeController
{
    public function __construct(
        private readonly PrefsService $prefsService,
    ) {}

    public function getTheme(string $uid): string
    {
        return $this->prefsService->getValue($uid, 'horde', 'theme') ?? 'default';
    }

    public function setTheme(string $uid, string $theme): void
    {
        $this->prefsService->setValue($uid, 'horde', 'theme', $theme);
    }
}
```

### 6.2 Reading the config default without a user row

`PrefsService::getValue()` returns the config-file default even when no DB row
exists.  The legacy `$prefs->getValue()` did the same, but only when
`$GLOBALS['registry']` was populated.  On a PSR-15 route the legacy call
would silently return `null`; the modern call always returns the default.

```php
// No DB row for this user -> returns 'en_US' from prefs.php
$lang = $this->prefsService->getValue($uid, 'horde', 'language');
```

### 6.3 Checking and honouring locks

```php
// Guard a write in code that was previously unaware of locking
if (!$this->prefsService->isLocked($uid, 'horde', 'theme')) {
    $this->prefsService->setValue($uid, 'horde', 'theme', $requested);
}

// Or let setValue() throw and catch it at the boundary
try {
    $this->prefsService->setValue($uid, 'horde', 'theme', $requested);
} catch (\RuntimeException $e) {
    // pref is locked. Ignore or surface to the caller
}
```

### 6.4 Bulk read of all prefs in a scope

```php
// Before
global $prefs;
$prefs->changeScope('nag');
$all = iterator_to_array($prefs);     // not actually supported cleanly

// After
$all = $this->prefsService->getAllInScope($uid, 'nag');
// Returns config defaults + user values, locked prefs at their config value
```

### 6.5 Migration checklist

When migrating a class that currently uses `$prefs` or `Horde_Core_Factory_Prefs`:

- [ ] Add `PrefsService` to the constructor.
- [ ] Replace every `$prefs->getValue($key)` with
  `$this->prefsService->getValue($uid, $scope, $key)`.
  Supply the real `$uid` (from auth context) and `$scope` (the app name).
- [ ] Replace `$prefs->setValue($key, $value)` with
  `$this->prefsService->setValue($uid, $scope, $key, $value)`.
- [ ] Replace `$prefs->isLocked($key)` with
  `$this->prefsService->isLocked($uid, $scope, $key)`.
- [ ] Remove any `global $prefs;` / `global $registry;` declarations that
  existed solely to reach preferences.
- [ ] Verify that the class no longer calls `appInit()` only to get `$prefs`.
- [ ] Update unit tests: inject `NullPrefsService` or a PHPUnit mock; no
  global bootstrap required.

### 6.6 What PrefsService does not replace

`PrefsService` is storage/retrieval only.  The following legacy features have
**no direct equivalent** in the interface and must be handled separately:

| Feature | Legacy mechanism | Modern path |
|---|---|---|
| Hooks (`'hook' => true`) | `Horde_Core_Prefs_Storage_Hooks` driver | Explicit hook invocation in application code |
| Session-scoped cache | `Horde_Prefs_Cache_HordeCache` | Not needed; `PrefsService` is stateless |
| Size callback | `$conf['prefs']['maxsize']` | Application-level validation before `setValue()` |
| Preference UI rendering | `Horde_Core_Prefs_Ui` | Unchanged; still uses legacy stack for forms |

---

## 7. Configuration Reference

`conf.php` knobs consumed by `PrefsServiceFactory`:

```php
$conf['prefs']['driver']          // 'sql' | 'ldap' | 'nosql' | 'null' | 'session'
$conf['prefs']['params']['table'] // SQL table name, default 'horde_prefs'
```

`PrefsConfigLoader` is configured automatically from the constants
`HORDE_CONFIG_BASE` (deployment config root) and `HORDE_BASE`
(path to `vendor/horde/horde`; parent directory is used as `vendorBase`).
Both constants are set during bootstrap; the factory falls back to
`/etc/horde` and `__DIR__/../../../..` respectively if they are not defined.
