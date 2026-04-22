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

/**
 * Immutable page metadata for HTML head and body tag generation.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
final class PageMeta
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $language = null,
        public readonly ?string $bodyClass = null,
        public readonly ?string $bodyId = null,
        public readonly ?string $htmlId = null,
        public readonly ?string $faviconUrl = null,
        public readonly bool $deferScripts = true,
    ) {}
}
