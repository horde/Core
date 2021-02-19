# The Horde 5 Bootstrapping Process

## Horde Base App Bootstrap (CLI and classic web pages)

### Early Bootstrap

    require_once __DIR__ . '/lib/Application.php';

Load the application core (before autoloading)
In Application.php, the first thing

    require_once __DIR__ . '/core.php';

(The rest of the file is class definition of Hore_Application)

In core.php

- up to line 33, config some sane PHP defaults mostly irrelevant by PHP 7
- Define HORDE_BASE constant unless already defined (see Other Horde App case)
- Add the base app's lib/ dir to include path (for relative require/include/_once)
- hook into horde base's config/horde.local.php if it exists
- Try to include Horde_Autoloader_Cache, otherwise Horde_Autoloader_Default (via relative require_once)
- This produces a global autoloader object in $__autoloader ... also autoloading lookup for Horde_* to base/lib dir is setup
  - In traditional H3 setups, almost all libs lived in horde/lib
- Register Horde_ErrorHandler as ErrorHandler and ExceptionHandler and ShutdownHandler (fatal errors)

### Horde_Registry::appInit()

This step must run after some basic autoloading and some path relations are set up

- Now, Horde_Registry::appInit() is *always* called. In the case of the base app, it's appInit('horde', $params)
- static appInit creates some environment before finally letting the constructor build the registry instance and install it to $GLOBALS['registry'].
- Calling appInit repeatedly just produces the stored object, but no side effects (does not restore globals!)
- Setup depends on call parameters.

- session control
  - disable starting sessions
  - readonly session
  - default RW sessions

- authentication
    - no auth (yet)
    - require auth
    - fallback: try auth, otherwise go unauthed

- cli mode
    - install global Horde_Cli object
    - no compression
    - no authentication
    - check and die if browser is detected

- permission checking
    - A page may require permissions to be accessed
    - a page may require "admin" privilege (from conf file)

- test script flag
    - allows to run incomplete and otherwise crippled environments

### The Registry Constructor

- Set timezone either from environment or fallback to UTC.
- Set up an initial bundle of horde_injector items
- Register the - already globalized - horde autoloader with the injector
- Initialize the horde base app's config
        /* Import and global Horde's configuration values. */
        $this->importConfig('horde');
- setup umask, error reporting level and other php constraints
- Setup a global Horde_Browser object
- Read registry config (including .local, .d, vhost if enabled)
- Register either a session or a null session globally and with injector
- Load the application/api registry
- Load language config
- initialize PageOutput and globalize
- setup notification system
- Add registry to shutdown handler


### After Registry is installed to global
- set the initialApp key
- get an instance of the application class
- run pushApp (fail and redo in fallback auth case)
- handle final failure of pushApp
- set timezone
- set pageoutput compression
- permission/admin checking
- return the application object

## Other Horde Application Bootstrap (CLI and classic web pages)

Pretty similar. 
- The application's lib/Application.php is required.
- a constant $Application_BASE is set up
- If HORDE_BASE is not defined, $app/config/horde.local.php is loaded which is supposed to define it
- Else HORDE_BASE is defined as the dir above app dir (which is wrong in composer setups)
- The application will require HORDE_BASE . /lib/core.php kernel
- appInit is called and will run exactly like described above, only the app specific configs will be merged with horde base config

## Rampage/Controller
- Regardless which app will fulfill the request, horde/base is bootstrapped.
- classic H5 does auth first, master branch defers auth depending on controller config
- Actual bootstrapping is just the same
- the found relevant app is later pushApp()ed
- authentication may be done either by requestMapper, not at all, or on the controller level

## RPC

## Ajax Framework