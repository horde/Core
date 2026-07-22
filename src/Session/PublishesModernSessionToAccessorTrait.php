<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */

namespace Horde\Core\Session;

use Throwable;

/**
 * Shared "publish a fresh HordeSession to the injector and the accessor"
 * behavior for both {@see \Horde_Session} and {@see \Horde_Session_Null}.
 *
 * Neither the parent shim nor the null shim can (or should) reach into
 * {@see SessionLifecycle::rebuildHordeSession()} directly. Both need
 * the same three-step operation:
 *
 *  1. Build a fresh {@see HordeSession} via the injector so encryption
 *     closures ({@see HordeSessionFactory}) are wired up.
 *  2. Pin it as the shared singleton so `getInstance(HordeSession::class)`
 *     returns the fresh value.
 *  3. Publish it to the {@see SessionAccessor} so consumers reading
 *     through {@see SessionAccess} see a current session.
 *
 * The trait is deliberately stateless: no properties, no `use` clashes,
 * no visibility relaxation on the classes that include it. Callers whose
 * class carries its own extra state (the parent shim's `explicitModern`
 * pin) handle those branches locally before delegating here.
 */
trait PublishesModernSessionToAccessorTrait
{
    /**
     * Build a fresh HordeSession via the injector, register it as the
     * shared singleton, and publish it to the SessionAccessor.
     *
     * No-op when no injector is present (bootstrap-only test contexts).
     * The SessionAccess binding may legitimately be missing on some
     * routes; the injector-singleton path still delivers the fresh
     * HordeSession to callers that look it up directly, so silence the
     * accessor exception rather than fail the whole publish.
     */
    private function publishModernSessionToAccessor(): void
    {
        if (!isset($GLOBALS['injector'])) {
            return;
        }

        $fresh = $GLOBALS['injector']->createInstance(HordeSession::class);
        $GLOBALS['injector']->setInstance(HordeSession::class, $fresh);

        try {
            $access = $GLOBALS['injector']->getInstance(SessionAccess::class);
            if ($access instanceof SessionAccessor) {
                $access->replaceWith($fresh);
            }
        } catch (Throwable) {
            // No SessionAccess binding wired. Consumers still reach the
            // fresh instance via getInstance(HordeSession::class) above.
        }
    }
}
