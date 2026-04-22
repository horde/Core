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

namespace Horde\Core\Sidebar;

use Horde\Core\Service\PrefsService;
use Horde\Injector\Attribute\Factory;
use Horde_Registry;
use Exception;

/**
 * Builds a SidebarData object via the legacy bridge (SidebarCollector).
 *
 * Creates a SidebarCollector, calls the current application's menu()
 * and sidebar() hooks through the registry, then converts the collected
 * mutable state into an immutable SidebarData.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[Factory(factory: SidebarBuilderFactory::class, method: 'create')]
class SidebarBuilder
{
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly PrefsService $prefs,
    ) {}

    /**
     * @param array<string, string> $cookieData Cookie values for container collapsed state
     */
    public function build(string $currentApp = 'horde', array $cookieData = []): SidebarData
    {
        $collector = new SidebarCollector();

        $this->populateFromApp($collector, $currentApp);

        $uid = $this->getAuthenticatedUser();
        $width = 150;
        $isRtl = false;

        if ($uid !== '') {
            $widthVal = $this->prefs->getValue($uid, 'horde', 'sidebar_width');
            if ($widthVal !== null && (int) $widthVal > 0) {
                $width = (int) $widthVal;
            }
        }

        try {
            $isRtl = (bool) ($this->registry->nlsconfig->curr_rtl ?? false);
        } catch (Exception) {
        }

        return $collector->toSidebarData($width, $isRtl, $cookieData);
    }

    private function populateFromApp(SidebarCollector $collector, string $app): void
    {
        try {
            $this->registry->callAppMethod($app, 'menu', [
                'args' => [$collector],
                'noperms' => true,
            ]);
        } catch (Exception) {
        }

        try {
            $this->registry->callAppMethod($app, 'sidebar', [
                'args' => [$collector],
                'noperms' => true,
            ]);
        } catch (Exception) {
        }
    }

    private function getAuthenticatedUser(): string
    {
        try {
            return $this->registry->getAuth() ?: '';
        } catch (Exception) {
            return '';
        }
    }
}
