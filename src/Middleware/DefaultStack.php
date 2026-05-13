<?php

declare(strict_types=1);

namespace Horde\Core\Middleware;

/**
 * Provides the default per-route middleware stack.
 *
 * This stack represents the full middleware chain between route resolution
 * and controller execution for a standard authenticated route:
 *
 * - HordeCore: bootstraps the Horde framework (registry, injector, factories)
 * - ErrorFilter: catches exceptions, prevents stack traces reaching users
 * - AuthHordeSession: identifies the authenticated user from the session
 * - RedirectToLogin: redirects unauthenticated users to the login page
 *
 * Routes should explicitly call ->withMiddleware(DefaultStack::get())
 * rather than relying on implicit fallback behavior in AppRouter.
 */
class DefaultStack
{
    public static function get(): array
    {
        return [
            HordeCore::class,
            ErrorFilter::class,
            AuthHordeSession::class,
            RedirectToLogin::class,
        ];
    }
}
