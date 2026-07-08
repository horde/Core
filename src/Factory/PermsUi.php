<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Perms\PermsUiInterface;
use Horde\Core\Perms\Ui as NegativeUi;
use Horde\Core\Uri\UriBuilderInterface;
use Horde\Injector\Injector;
use Horde\Token\Token;
use Horde_Auth_Base;
use Horde_Core_Perms;
use Horde_Core_Perms_Ui;
use Horde_Group;
use Horde_Perms_Base;
use Horde_Registry;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Factory selecting the administrative permission UI implementation.
 *
 * Reads the perms.negative_permissions configswitch. When set to 'on',
 * returns Horde\Core\Perms\Ui, the Horde_View based responsive form
 * that lets administrators grant, leave neutral, or deny each
 * permission bit and stores those denies in Horde_Perms 3.1's deny
 * cascade. Any other value (including the default 'off') returns the
 * classic Horde_Core_Perms_Ui with its grant-only checkbox form.
 *
 * Config resolution goes through the modern ConfigLoader first.
 * $GLOBALS['conf'] is consulted only as a BC fallback for callers
 * running under the legacy bootstrap without a functional loader.
 * See negativePermissionsEnabled() for the full order.
 */
class PermsUi
{
    /**
     * Builds a permissions UI for the given Horde_Perms backend.
     *
     * @return PermsUiInterface|Horde_Core_Perms_Ui  The concrete UI.
     *         The classic implementation does not yet implement the
     *         interface, so consumers accepting either shape must
     *         type-hint on the union.
     */
    public function create(
        Injector $injector,
        Horde_Perms_Base $perms,
        Horde_Core_Perms $corePerms
    ): PermsUiInterface|Horde_Core_Perms_Ui {
        return $this->negativePermissionsEnabled($injector)
            ? $this->createWithDenies($injector, $perms, $corePerms)
            : $this->createGrantOnly($perms, $corePerms);
    }

    private function createGrantOnly(
        Horde_Perms_Base $perms,
        Horde_Core_Perms $corePerms
    ): Horde_Core_Perms_Ui {
        return new Horde_Core_Perms_Ui($perms, $corePerms);
    }

    private function createWithDenies(
        Injector $injector,
        Horde_Perms_Base $perms,
        Horde_Core_Perms $corePerms
    ): NegativeUi {
        $templatePath = $this->resolveTemplatePath($injector);
        $groups = $this->resolveGroups($injector);
        $auth = $this->resolveAuth($injector);
        $request = $injector->getInstance(ServerRequestInterface::class);
        $tokens = $injector->getInstance(Token::class);
        $uriBuilder = $injector->getInstance(UriBuilderInterface::class);
        $registry = $injector->getInstance(Horde_Registry::class);
        $notification = $this->resolveNotification($injector);

        return new NegativeUi(
            $perms,
            $corePerms,
            $templatePath,
            $request,
            $tokens,
            $uriBuilder,
            $registry,
            $notification,
            $groups,
            $auth
        );
    }

    /**
     * Resolves the notification handler for surfacing errors like
     * "invalid CSRF token" back to the admin. Returns null when the
     * injector can't supply one so the modern UI degrades to silent
     * rejection rather than failing to construct.
     */
    private function resolveNotification(Injector $injector): ?\Horde_Notification_Handler
    {
        try {
            $handler = $injector->getInstance('Horde_Notification');
            return $handler instanceof \Horde_Notification_Handler ? $handler : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolves the templates directory. Templates for the admin perms
     * dialog live under horde/base's templates tree, keyed by the
     * 'horde' app in the registry.
     */
    private function resolveTemplatePath(Injector $injector): string
    {
        $registry = $injector->getInstance(Horde_Registry::class);
        return $registry->get('templates', 'horde') . '/admin/perms';
    }

    /**
     * Resolves the group backend for the group picker. Returns null
     * when unavailable so the modern UI degrades to "no groups"
     * rather than fail rendering.
     */
    private function resolveGroups(Injector $injector): ?Horde_Group
    {
        try {
            $groups = $injector->getInstance(Horde_Group::class);
            return $groups instanceof Horde_Group ? $groups : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Resolves an auth driver for the user picker. Returns null when
     * unavailable so the picker degrades to a free-text input rather
     * than fail rendering.
     */
    private function resolveAuth(Injector $injector): ?Horde_Auth_Base
    {
        try {
            $factory = $injector->getInstance('Horde_Core_Factory_Auth');
            $auth = $factory->create();
            return $auth instanceof Horde_Auth_Base ? $auth : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Reads the negative_permissions switch.
     *
     * Preferred source is ConfigLoader (the modern rampage config
     * pipeline). $GLOBALS['conf'] is consulted only as a BC fallback
     * for callers running under the legacy bootstrap where the modern
     * loader is unavailable or empty. Final default is off so behavior
     * on upgrade matches the pre-3.1 UI.
     *
     * New code should not reach for $GLOBALS['conf'] directly. The
     * fallback lives here because base/admin/perms/*.php still runs
     * under the legacy bootstrap and there is no other bridge yet.
     */
    private function negativePermissionsEnabled(Injector $injector): bool
    {
        try {
            $loader = $injector->getInstance(ConfigLoader::class);
            $state = $loader->load('horde');
            $modern = $state->get('perms.negative_permissions', null);
            if ($modern !== null) {
                return (string) $modern === 'on';
            }
        } catch (\Throwable $e) {
            // ConfigLoader unavailable. Fall through to the legacy
            // globals lookup below.
        }

        // BC fallback for the legacy $conf bootstrap. Do not add new
        // $GLOBALS['conf'] reads elsewhere in this codebase. Pass the
        // value in through the constructor instead.
        if (isset($GLOBALS['conf']['perms']['negative_permissions'])) {
            return (string) $GLOBALS['conf']['perms']['negative_permissions'] === 'on';
        }

        return false;
    }
}
