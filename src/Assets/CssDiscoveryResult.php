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

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<int, CssAssetEntry>
 */
final class CssDiscoveryResult implements IteratorAggregate, Countable
{
    /** @param list<CssAssetEntry> $entries */
    public function __construct(
        private readonly array $entries,
        private readonly string $theme,
        private readonly string $app,
    ) {}

    /** @return ArrayIterator<int, CssAssetEntry> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->entries);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function getApp(): string
    {
        return $this->app;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /** @return list<CssAssetEntry> */
    public function toArray(): array
    {
        return $this->entries;
    }
}
