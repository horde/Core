<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Service;

/**
 * Paginated group list result
 *
 * Contains a page of groups plus pagination metadata.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class GroupListResult
{
    /**
     * Constructor
     *
     * @param GroupInfo[] $groups Array of GroupInfo objects for this page
     * @param int $total Total number of groups across all pages
     * @param int $page Current page number (1-indexed)
     * @param int $perPage Number of groups per page
     * @param bool $hasNext Whether there are more pages after this one
     * @param bool $hasPrev Whether there are pages before this one
     */
    public function __construct(
        public readonly array $groups,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly bool $hasNext,
        public readonly bool $hasPrev
    ) {}
}
