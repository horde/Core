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

/**
 * Value object holding the result of route compilation.
 */
class RoutesCompilerResult
{
    public function __construct(
        public readonly int $appCount,
        public readonly int $routeCount,
        public readonly string $outputFile,
        public readonly array $compiled,
    ) {}
}
