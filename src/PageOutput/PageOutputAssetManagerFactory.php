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

namespace Horde\Core\PageOutput;

use Horde_Injector;
use Horde\Injector\Injector;

/**
 * Factory for PageOutputAssetManager.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class PageOutputAssetManagerFactory
{
    public function create(Horde_Injector|Injector $injector): PageOutputAssetManager
    {
        $assetCollector = $injector->getInstance(AssetCollector::class);

        return new PageOutputAssetManager($assetCollector);
    }
}
