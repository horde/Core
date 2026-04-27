<?php

declare(strict_types=1);

namespace Horde\Core\Api;

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */
final class ApiRegistryCaller
{
    public function __construct(
        private readonly ApiRegistry $registry,
        private readonly string $interface,
    ) {}

    /**
     * @param list<mixed> $args
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->registry->invoke($this->interface . '.' . $method, $args)->value;
    }
}
