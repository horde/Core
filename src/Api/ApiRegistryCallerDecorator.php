<?php

declare(strict_types=1);

namespace Horde\Core\Api;

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */
final class ApiRegistryCallerDecorator
{
    public function __construct(
        private readonly ApiRegistry $registry,
    ) {}

    public function __get(string $interface): ApiRegistryCaller
    {
        return new ApiRegistryCaller($this->registry, $interface);
    }
}
