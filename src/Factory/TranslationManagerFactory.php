<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Factory;

use Horde\Core\Translation\TranslationManager;
use Horde_Injector;
use Horde_Registry;
use Exception;

/**
 * Factory for TranslationManager.
 *
 * Preloads the manager with translation domains from all registered
 * Horde applications. Each app with a locale/ directory gets its own
 * domain entry.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class TranslationManagerFactory
{
    /**
     * Create and preload a TranslationManager.
     *
     * @param Horde_Injector $injector  The dependency injector.
     *
     * @return TranslationManager  Preloaded with all app domains.
     */
    public function create(Horde_Injector $injector): TranslationManager
    {
        $manager = new TranslationManager();
        $registry = $injector->getInstance(Horde_Registry::class);

        foreach ($registry->listAllApps() as $app) {
            try {
                $fileroot = $registry->get('fileroot', $app);
            } catch (Exception) {
                continue;
            }
            if ($fileroot && is_dir($fileroot . '/locale')) {
                $manager->addDomain($app, $fileroot . '/locale');
            }
        }

        return $manager;
    }
}
