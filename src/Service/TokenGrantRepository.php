<?php
declare(strict_types=1);

namespace Horde\Core\Service;

interface TokenGrantRepository
{
    /**
     * Find the shared grant for a "GrantStrategy::Additive" strategy lookup.
     * Returns the single grant that is not exclusively owned by an Isolated
     * ServiceAuthorization or null if none exists.
     */
    public function findShared(string $userId, string $providerId): ?TokenGrant;

    /** Find any grant by its stable UUID. */
    public function findById(string $grantId): ?TokenGrant;

    /** Find all grants for a (userId, providerId) pair. */
    public function findAll(string $userId, string $providerId): array;

    /** Persist a new TokenGrant. */
    public function save(TokenGrant $grant): void;

    /**
     * Persist changes to an existing grant (e.g. refreshed access token or
     * extended scope set after an Additive flow).
     */
    public function update(TokenGrant $grant): void;

    /** Delete a grant row. Caller must ensure no ServiceAuthorization references it. */
    public function delete(TokenGrant $grant): void;
}
