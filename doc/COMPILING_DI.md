# Compiling a Dependency Injection Binding Map

Horde's Injector resolves dependencies at runtime through autowiring and
factory/implementation bindings registered during bootstrap. This works
correctly but means every request repeats the same reflection and binder
lookups. A **compiled binding map** captures the result of those lookups as a
plain PHP array that opcache can keep in shared memory, eliminating the
repeated work on subsequent requests.

Building a complete map is a progressive process: different pages exercise
different code paths and register different bindings. You run profiling across
representative requests, merge the results and then ship the final map as a
static config file.

## Prerequisites

The `horde/event-dispatcher` package must be installed:

```
composer require horde/event-dispatcher
```

It is listed as a suggestion in `horde/injector` and is not required at
runtime. The profiling code path is guarded — if the package is missing, a
warning is logged and the application continues normally.

## Step 1: Enable profiling

Set the `HORDE_INJECTOR_PROFILE` environment variable before a request:

```bash
# Apache — add to virtualhost or .htaccess
SetEnv HORDE_INJECTOR_PROFILE 1

# nginx — add to location block
fastcgi_param HORDE_INJECTOR_PROFILE 1;

# CLI
HORDE_INJECTOR_PROFILE=1 php horde/index.php
```

By default the binding map is written to
`sys_get_temp_dir()/horde_injector_bindings.php`. Override the path with a
second variable:

```bash
HORDE_INJECTOR_PROFILE=1 \
HORDE_INJECTOR_PROFILE_PATH=/var/cache/horde/bindings.php \
php horde/index.php
```

## Step 2: Exercise the application

Each request dumps the bindings that were registered during that request.
To build a complete map you need to hit every code path that registers
bindings:

1. **Log in** to Horde (creates session, auth prefs bindings).
2. **Visit each application** — at minimum load the main page of every
   installed app (IMP, Kronolith, Turba, etc.).
3. **Trigger background tasks** — run any CLI cron jobs or AJAX endpoints
   that register additional factories.
4. **Exercise administrative pages** — configuration, user management and
   permission screens often bind factories that normal pages do not.

Each request overwrites the output file. To accumulate bindings across
requests, copy or merge files between runs (see Step 3).

## Step 3: Merge multiple runs

Each dump is a valid PHP file that returns an associative array:

```php
<?php
declare(strict_types=1);
return [
    'Horde_Cache' => ['Horde_Core_Factory_Cache', 'create'],
    'Horde_Db_Adapter' => ['Horde_Core_Factory_Db', 'create'],
    'Horde_Group' => 'Horde_Group_Sql',
    // ...
];
```

To combine multiple dumps into a single map:

```php
<?php
// merge_bindings.php
$merged = [];
foreach (glob('/var/cache/horde/bindings_*.php') as $file) {
    $merged = array_merge($merged, require $file);
}

$writer = new \Horde\Injector\BindingMapWriter();
$writer->write('/var/cache/horde/bindings_merged.php', $merged);
```

Later runs add new entries without losing earlier ones.

## Step 4: Load the compiled map

Pass the binding map to the Injector constructor:

```php
$bindings = require '/var/cache/horde/bindings_merged.php';
$injector = new Horde\Injector\Injector(new Horde\Injector\TopLevel(), $bindings);
```

Or load it after construction:

```php
$injector->loadBindings(require '/var/cache/horde/bindings_merged.php');
```

Bindings loaded this way are additive — they do not remove existing bindings,
and runtime `bindFactory()`/`bindImplementation()` calls still override them.

## Step 5: Disable profiling

Remove the environment variables once you have a satisfactory map. With
profiling disabled the Injector's event dispatcher slot remains `null` and
there is zero runtime overhead — the dispatch call sites are guarded by a
null check that the branch predictor eliminates.

## What gets captured (and what does not)

The binding map captures two types of bindings:

- **Implementation bindings** — `'InterfaceName' => 'ConcreteClass'`
- **Factory bindings** — `'InterfaceName' => ['FactoryClass', 'method']`

Closure bindings cannot be serialized to a PHP array file. They are reported
separately in the Horde log at DEBUG level:

```
Injector profiling: 3 uncacheable bindings: Horde\Util\Variables, ...
```

Review these after profiling. If a Closure binding is performance-critical,
consider converting it to a factory class so it can be included in the
compiled map.

## Binding map format reference

The array format is intentionally simple for opcache efficiency:

| Value type | Meaning | Example |
|---|---|---|
| `string` | Implementation binding | `'Horde_Cache' => 'Horde_Cache_Storage_File'` |
| `[string, string]` | Factory binding | `'Horde_Db_Adapter' => ['Horde_Core_Factory_Db', 'create']` |

This is the same format accepted by `Injector::loadBindings()` and produced
by `BindingMapWriter::write()`.

## Troubleshooting

**"Injector profiling failed to initialize"** (logged at WARN level)
The EventDispatcher could not be resolved. Verify that `horde/event-dispatcher`
is installed and its factory is registered in the Injector.

**Output file is not created**
Check that the output directory is writable by the web server process. If
using the default path, verify `sys_get_temp_dir()` returns a writable
directory.

**Missing bindings after loading the map**
The map only contains bindings that were registered during profiled requests.
Exercise additional code paths and merge the results. Some bindings are
registered conditionally (e.g., based on configuration or installed apps) and
will only appear when those conditions are met.

**Application behaves differently with the compiled map**
The compiled map provides the same bindings that would have been registered
at runtime. If behavior differs, a Closure binding that performs
request-specific logic may have been replaced by a static Implementation
binding. Check the uncacheable list in the profiling log.
