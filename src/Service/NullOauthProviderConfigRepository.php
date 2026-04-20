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

use Horde\Core\Service\Exception\OauthProviderConfigNotFoundException;

/**
 * Null provider config repository — safe default when no storage is configured.
 *
 * No providers ever exist. Saves and deletes are silently discarded.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class NullOauthProviderConfigRepository implements OauthProviderConfigRepository
{
    public function get(string $providerId): array
    {
        throw new OauthProviderConfigNotFoundException(
            "No provider config for '{$providerId}'"
        );
    }

    public function listAll(): array
    {
        return [];
    }

    public function listEnabled(): array
    {
        return [];
    }

    public function save(string $providerId, array $data): void {}

    public function delete(string $providerId): void {}

    public function exists(string $providerId): bool
    {
        return false;
    }
}
