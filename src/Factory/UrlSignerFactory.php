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

namespace Horde\Core\Factory;

use Horde\Core\Assets\HmacUrlSigner;
use Horde\Core\Assets\NullUrlSigner;
use Horde\Core\Assets\UrlSigner;
use Horde\Injector\Injector;

class UrlSignerFactory
{
    public function create(Injector $injector): UrlSigner
    {
        $conf = $GLOBALS['conf'] ?? [];

        if (!isset($conf['secret_key']) || $conf['secret_key'] === '') {
            return new NullUrlSigner();
        }

        $lifetime = (int) ($conf['urls']['hmac_lifetime'] ?? 30);

        return new HmacUrlSigner($conf['secret_key'], $lifetime);
    }
}
