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
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Prefs\Storage;

use Horde\Core\Config\PrefsConfigLoader;
use Horde_Prefs_Storage_Base;
use Throwable;
use Horde_Prefs_Scope;

/**
 * Horde_Prefs storage driver backed by PrefsConfigLoader
 *
 * Replaces Horde_Core_Prefs_Storage_Configuration as the config layer in
 * Horde_Prefs driver stacks. Reads preference defaults and lock flags from
 * the five-layer cascade (vendor -> deployment base -> prefs.d/* snippets ->
 * prefs.local.php -> prefs-$vhost.php) without touching $GLOBALS['registry'].
 *
 * This is intentionally read-only: store() and remove() are no-ops because
 * config files are never written at runtime.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class PrefsConfigLoaderStorage extends Horde_Prefs_Storage_Base
{
    /**
     * Pref names that carry a hook. Keyed by scope.
     *
     * Populated during get() so that callers (e.g. Horde_Core_Prefs_Storage_Hooks)
     * can discover them without re-reading config files.
     *
     * @var array<string, list<string>>
     */
    public array $hooks = [];

    /**
     * Pref types that are UI-only and carry no storable value.
     *
     * Public so that other parts of the config cascade (e.g. SqlPrefsService)
     * can reuse the same authoritative list without duplicating it.
     */
    public const UI_TYPES = ['link', 'prefslink', 'rawhtml', 'container', 'special'];

    /**
     * Constructor
     *
     * @param string            $uid    User ID (passed through to parent)
     * @param PrefsConfigLoader $loader Config loader supplying the cascade
     */
    public function __construct(string $uid, private PrefsConfigLoader $loader)
    {
        // Parent stores $uid as $this->_user and $params as $this->_params
        parent::__construct($uid, []);
    }

    /**
     * Load preference defaults and lock flags from config files into $scope_ob.
     *
     * Applied before the SQL driver so that SQL user values win for unlocked
     * prefs; locked prefs have Horde_Prefs_Scope::setLocked(true) set here and
     * the subsequent SQL driver is instructed by Horde_Prefs to skip them.
     *
     * If the config files cannot be loaded (missing app, IO error, parse error)
     * $scope_ob is returned unchanged. Same fallback as in the legacy Horde_Core_Prefs_Storage_Configuration.
     *
     * @param Horde_Prefs_Scope $scope_ob Scope object to populate
     * @return Horde_Prefs_Scope Populated (or unchanged on error) scope object
     */
    public function get($scope_ob)
    {
        try {
            $state = $this->loader->load($scope_ob->scope);
        } catch (Throwable) {
            // Config files missing or unreadable. Degrade gracefully.
            return $scope_ob;
        }

        foreach ($state->getAllPrefs() as $name => $pref) {
            // Skip UI-only entries that hold no storable value
            if (!isset($pref['value'])) {
                continue;
            }
            if (isset($pref['type']) && in_array($pref['type'], self::UI_TYPES, true)) {
                continue;
            }

            $scope_ob->set($name, $pref['value']);

            if (!empty($pref['locked'])) {
                $scope_ob->setLocked($name, true);
            }

            if (!empty($pref['hook'])) {
                $this->hooks[$scope_ob->scope][] = $name;
            }
        }

        return $scope_ob;
    }

    /**
     * No-op: config files are read-only at runtime.
     *
     * @param Horde_Prefs_Scope $scope_ob Ignored
     */
    public function store($scope_ob): void
    {
        // Config files are never written at runtime.
    }

    /**
     * No-op: config files are read-only at runtime.
     *
     * @param string|null $scope Ignored
     * @param string|null $pref  Ignored
     */
    public function remove($scope = null, $pref = null): void
    {
        // Config files are never written at runtime.
    }
}
