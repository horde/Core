<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Config;

use Horde\Injector\Injector;

/**
 * DI factory for {@see State}.
 *
 * Resolves the `horde` app's `conf.php` via {@see ConfigLoader}
 * rather than depending on `$GLOBALS['conf']`. Modern PSR-15 routes
 * that bypass `HordeCore` middleware do not get `$GLOBALS['conf']`
 * populated; without this factory, autowired consumers of `State`
 * (e.g. {@see LoggerConfig}) blow up with "Config neither passed
 * nor available from global".
 *
 * The fallback in `State::__construct(?array $conf = null)` to
 * `$GLOBALS['conf']` continues to work for legacy callers that
 * construct `State` directly. The injector path goes through this
 * factory and skips the fallback entirely.
 */
class StateFactory
{
    public function create(Injector $injector): State
    {
        // Prefer a globally populated conf if it's already there:
        // legacy stacks ran appInit() and wrote $GLOBALS['conf']
        // before any DI lookup. Honour that to avoid double-loading.
        if (isset($GLOBALS['conf']) && is_array($GLOBALS['conf'])) {
            return new State($GLOBALS['conf']);
        }

        // Modern path: ConfigLoader reads conf.php directly from disk.
        return $injector->getInstance(ConfigLoader::class)->load('horde');
    }
}
