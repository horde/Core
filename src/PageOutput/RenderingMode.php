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

enum RenderingMode: string
{
    case BASIC = 'basic';
    case DYNAMIC = 'dynamic';
    case RESPONSIVE = 'responsive';

    public function isDesktop(): bool
    {
        return $this === self::BASIC || $this === self::DYNAMIC;
    }

    public function toViewMode(): ViewMode
    {
        return match ($this) {
            self::BASIC => ViewMode::BASIC,
            self::DYNAMIC => ViewMode::DYNAMIC,
            self::RESPONSIVE => ViewMode::BASIC,
        };
    }
}
