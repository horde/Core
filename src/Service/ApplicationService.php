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

namespace Horde\Core\Service;

use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Config\RegistryState;
use Horde\Core\Util\VersionReader;

/**
 * Application introspection service
 *
 * Provides metadata about installed Horde applications without using
 * the user-centric Horde_Registry object.
 *
 * Phase 1: Basic composer + registry config level introspection
 * Future: Readiness checks, resource discovery
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class ApplicationService
{
    private RegistryState $registryState;

    public function __construct(
        RegistryConfigLoader $registryLoader
    ) {
        $this->registryState = $registryLoader->load();
    }

    /**
     * List all applications with metadata
     *
     * @param bool $includeNonApps Include non-app entries (headings, etc.)
     * @return array Array of application data
     */
    public function listApplications(bool $includeNonApps = false): array
    {
        $apps = [];
        foreach ($this->registryState->listApplications() as $appName) {
            $appData = $this->registryState->getApplication($appName);
            if ($appData) {
                $appInfo = $this->buildApplicationInfo($appName, $appData);

                // Filter out non-apps unless requested
                if (!$includeNonApps && !$appInfo['isApp']) {
                    continue;
                }

                $apps[] = $appInfo;
            }
        }
        return $apps;
    }

    /**
     * Get single application by name
     *
     * @param string $name App name
     * @return array|null App data or null if not found
     */
    public function getApplication(string $name): ?array
    {
        $appData = $this->registryState->getApplication($name);
        if (!$appData) {
            return null;
        }
        return $this->buildApplicationInfo($name, $appData);
    }

    /**
     * Build application info array from registry data
     *
     * Phase 1: Basic composer + registry level introspection
     *
     * Status types from registry.php documentation:
     * - active: Activate application (default)
     * - admin: Activate application, but only for admins
     * - heading: Header label for application groups (NOT an app)
     * - hidden: Enable application, but hide
     * - inactive: Disable application
     * - link: Add a link to an external url (NOT a Horde app)
     * - noadmin: Disable application for authenticated admins
     * - notoolbar: Activate application, but hide from menus
     * - topbar: Show in topbar only (NOT an app, widget referencing app)
     *
     * @param string $name App name
     * @param array $data Registry data
     * @return array Application metadata
     */
    private function buildApplicationInfo(string $name, array $data): array
    {
        $status = $data['status'] ?? 'active'; // Default is 'active' per docs

        // Determine if this is an actual app or just a UI element
        // Non-apps: heading (grouping), topbar (widget), link (external URL)
        $isApp = !in_array($status, ['heading', 'topbar', 'link']);

        return [
            'name' => $name,
            'displayName' => $data['name'] ?? $name, // Translated name from registry
            'version' => $this->getVersion($name, $data),
            'status' => $status,
            'isApp' => $isApp,
            'active' => in_array($status, ['active', 'admin']),
            'ready' => in_array($status, ['active', 'admin']), // Phase 1: stub
            'webroot' => $data['webroot'] ?? '',
            'fileroot' => $data['fileroot'] ?? '',
        ];
    }

    /**
     * Get application version from .horde.yml
     *
     * Phase 1: Read from .horde.yml only
     * Future phases: Support composer.json fallback
     *
     * @param string $name App name
     * @param array $data Registry data
     * @return string Version string
     */
    private function getVersion(string $name, array $data): string
    {
        // Check registry data first (rarely present)
        if (isset($data['version'])) {
            return $data['version'];
        }

        // Try to read from .horde.yml (primary source)
        if (isset($data['fileroot'])) {
            $version = VersionReader::readVersionFromFileroot($data['fileroot']);
            if ($version) {
                return $version;
            }
        }

        return 'unknown';
    }
}
