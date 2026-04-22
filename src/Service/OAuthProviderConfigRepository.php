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

use Horde\Core\Factory\OAuthProviderConfigRepositoryFactory;
use Horde\Injector\Attribute\Factory;

/**
 * Storage contract for OAuth provider configurations.
 *
 * Providers are keyed by a unique slug (provider_id).
 * Data is returned as plain arrays with snake_case keys matching DB columns,
 * because the three provider types (oauth2, oidc, service_app) have
 * different shapes.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[Factory(factory: OAuthProviderConfigRepositoryFactory::class, method: 'create')]
interface OAuthProviderConfigRepository
{
    /**
     * @throws Exception\OAuthProviderConfigNotFoundException
     * @return array<string, mixed>
     */
    public function get(string $providerId): array;

    /** @return list<array<string, mixed>> */
    public function listAll(): array;

    /** @return list<array<string, mixed>> */
    public function listEnabled(): array;

    /** @param array<string, mixed> $data */
    public function save(string $providerId, array $data): void;

    public function delete(string $providerId): void;

    public function exists(string $providerId): bool;
}
