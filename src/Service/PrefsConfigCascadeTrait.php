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

namespace Horde\Core\Service;

use Horde\Core\Prefs\Storage\PrefsConfigLoaderStorage;
use RuntimeException;

/**
 * Shared five-layer config cascade for PrefsService implementations.
 *
 * Provides the config-file cascade (vendor → deployment base → prefs.d/*
 * snippets → local → vhost) as reusable building blocks for any storage
 * backend.
 *
 * ## Requirements
 *
 * The consuming class MUST declare:
 *
 *   private Horde\Core\Config\PrefsConfigLoader $prefsConfigLoader;
 *
 * ## Provided methods
 *
 * Public (fulfils PrefsService interface contract):
 *   isLocked(string $uid, string $scope, string $key): bool
 *
 * Protected helpers for consuming class:
 *   assertNotLocked(string $scope, string $key): void
 *   buildConfigSeed(string $scope): array
 *   configDefault(string $scope, string $key): mixed
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
trait PrefsConfigCascadeTrait
{
    /**
     * True if the pref is locked by a config layer.
     *
     * Lock state is entirely deployment-wide; $uid is accepted only to
     * satisfy the PrefsService interface contract and has no effect.
     *
     * @param string $uid   User ID (lock state is not per-user so this is ignored)
     * @param string $scope App name
     * @param string $key   Preference key
     * @return bool True if the pref is locked in config
     */
    public function isLocked(string $uid, string $scope, string $key): bool
    {
        $pref = $this->prefsConfigLoader->load($scope)->getPref($key);
        return $pref !== null && !empty($pref['locked']);
    }

    /**
     * Throw RuntimeException if the pref is locked in config.
     *
     * Call at the very start of setValue() before touching the storage
     * backend to prevent stale locked rows from accumulating.
     *
     * @param string $scope App name
     * @param string $key   Preference key
     * @throws RuntimeException If the pref is locked in config
     */
    protected function assertNotLocked(string $scope, string $key): void
    {
        $pref = $this->prefsConfigLoader->load($scope)->getPref($key);
        if ($pref !== null && !empty($pref['locked'])) {
            throw new RuntimeException(
                "Preference '$key' in scope '$scope' is locked by configuration and cannot be modified."
            );
        }
    }

    /**
     * Build the config-defaults seed for getAllInScope().
     *
     * Iterates every pref in the merged five-layer config state and returns:
     *
     *   'defaults' key => value for every storable, non-UI-only pref that
     *                carries a 'value' key in config.
     *   'locked'   set of keys (key => true) locked in config.
     *
     * UI-only types (link, prefslink, rawhtml, container, special) and prefs
     * with no 'value' are excluded from 'defaults'.
     *
     * The result is intended as the initial result array for getAllInScope().
     * The backend implementation then overlays user-specific rows while
     * honouring the 'locked' set.
     *
     * @param  string $scope App name
     * @return array{defaults: array<string, mixed>, locked: array<string, true>}
     */
    protected function buildConfigSeed(string $scope): array
    {
        $state    = $this->prefsConfigLoader->load($scope);
        $defaults = [];
        $locked   = [];

        foreach ($state->getAllPrefs() as $name => $pref) {
            if (isset($pref['type']) && in_array($pref['type'], PrefsConfigLoaderStorage::UI_TYPES, true)) {
                continue;
            }
            if (!isset($pref['value'])) {
                continue;
            }
            $defaults[$name] = $pref['value'];
            if (!empty($pref['locked'])) {
                $locked[$name] = true;
            }
        }

        return ['defaults' => $defaults, 'locked' => $locked];
    }

    /**
     * Return the config-layer default for a single pref, or null.
     *
     * Returns null when:
     *   - The pref is not defined in config.
     *   - The config definition has no 'value' key.
     *   - The pref type is UI-only (link, prefslink, rawhtml, container, special).
     *
     * Use as the fallback in getValue() when the storage backend returns null,
     * and as the definitive value for locked prefs (skip the backend entirely).
     *
     * @param string $scope App name
     * @param string $key   Preference key
     * @return mixed Config-layer default or null
     */
    protected function configDefault(string $scope, string $key): mixed
    {
        $pref = $this->prefsConfigLoader->load($scope)->getPref($key);
        if ($pref === null || !isset($pref['value'])) {
            return null;
        }
        if (isset($pref['type']) && in_array($pref['type'], PrefsConfigLoaderStorage::UI_TYPES, true)) {
            return null;
        }
        return $pref['value'];
    }
}
