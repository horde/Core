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

namespace Horde\Core\Assets;

final class CssDiscoveryRequest
{
    /** @param list<string> $files */
    public function __construct(
        public readonly array $files = ['screen.css'],
        public readonly string $app = 'horde',
        public readonly string $theme = 'default',
        public readonly ?string $subView = null,
    ) {}
}
