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

namespace Horde\Core\Path;

use SplFileInfo;
use Stringable;

interface PathBuilderInterface extends Stringable
{
    public function withComponentRoot(): static;

    public function withAppFileroot(string $app): static;

    public function withAppThemesDir(string $app): static;

    public function withAppJsDir(string $app): static;

    public function withStaticDir(): static;

    public function withConfigDir(?string $app = null): static;

    public function withTmpDir(): static;

    public function withSlug(string $slug): static;

    public function withPart(string $part): static;

    public function toSplFileInfo(): SplFileInfo;
}
