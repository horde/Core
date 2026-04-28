<?php

declare(strict_types=1);

/**
 * Modern factory for Horde_Core_Ajax_Application instances.
 *
 * Resolves the per-app Ajax Application class using the same naming
 * convention as the legacy Horde_Core_Factory_Ajax (PSR-4 first, then
 * legacy underscore naming), but does not extend the legacy
 * Factory_Base or pull dependencies from globals.
 *
 * The Application constructor still reads $registry and $session from
 * globals internally — that cannot change without touching every app's
 * subclass. This factory's value is that the *caller* (the PSR-15
 * controller) supplies parameters from the request rather than from
 * procedural script context.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @package   Core
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use Horde\Exception\HordeRuntimeException;
use Horde\Util\Variables;
use Horde_Core_Ajax_Application;
use Horde_Variables;

class AjaxApplicationFactory
{
    /**
     * Create an Ajax Application instance for the given app.
     *
     * Resolves the class name in priority order:
     * 1. PSR-4: Horde\{Ucfirst_app}\Ajax\Application
     * 2. Legacy: {App}_Ajax_Application
     *
     * @param string                      $app    Application name (lowercase)
     * @param Horde_Variables|Variables    $vars   Request variables
     * @param string|null                 $action The AJAX action to perform
     * @param string|null                 $token  Session CSRF token
     *
     * @return Horde_Core_Ajax_Application
     *
     * @throws HordeRuntimeException When no Ajax Application class exists for the app
     */
    public function create(
        string $app,
        Horde_Variables|Variables $vars,
        ?string $action = null,
        ?string $token = null,
    ): Horde_Core_Ajax_Application {
        $class = $this->resolveClass($app);
        if ($class === null) {
            throw new HordeRuntimeException(
                'Ajax Application class for "' . $app . '" not found.'
            );
        }

        return new $class($app, $vars, $action, $token);
    }

    /**
     * Resolve the Ajax Application class name for an app.
     *
     * Returns null if no class exists. Does not instantiate.
     *
     * @param string $app  Application name (lowercase)
     *
     * @return class-string<Horde_Core_Ajax_Application>|null
     */
    public function resolveClass(string $app): ?string
    {
        $psr4 = 'Horde\\' . ucfirst($app) . '\\Ajax\\Application';
        if (class_exists($psr4)) {
            return $psr4;
        }

        $legacy = $app . '_Ajax_Application';
        if (class_exists($legacy)) {
            return $legacy;
        }

        return null;
    }
}
