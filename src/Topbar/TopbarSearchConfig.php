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

namespace Horde\Core\Topbar;

/**
 * Configuration for the topbar search form.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class TopbarSearchConfig
{
    /**
     * @param string $action Form action URL
     * @param string $label Search field placeholder text
     * @param string $iconUrl Search button image URL
     * @param array<string, string> $parameters Hidden form field name/value pairs
     * @param bool $hasMenu Whether search has a dropdown menu
     */
    public function __construct(
        public readonly string $action,
        public readonly string $label,
        public readonly string $iconUrl,
        public readonly array $parameters = [],
        public readonly bool $hasMenu = false,
    ) {}
}
