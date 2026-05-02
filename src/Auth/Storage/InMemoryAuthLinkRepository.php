<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license http://www.horde.org/licenses/lgpl21 LGPL-2.1
 */

namespace Horde\Core\Auth\Storage;

use DateTimeImmutable;
use Horde\Horde\Service\AuthLink;
use Horde\Horde\Service\AuthLinkRepository;

/**
 * In-memory AuthLinkRepository for environments where neither SQL nor
 * filesystem storage is available.
 *
 * Data is lost when the process ends. Useful as a last-resort fallback
 * so the system can boot without errors.
 */
class InMemoryAuthLinkRepository implements AuthLinkRepository
{
    /** @var list<AuthLink> */
    private array $links = [];
    private int $nextId = 1;

    public function resolve(string $provider, string $externalId): ?AuthLink
    {
        foreach ($this->links as $link) {
            if ($link->provider === $provider && $link->externalId === $externalId) {
                return $link;
            }
        }

        return null;
    }

    /** @return list<AuthLink> */
    public function findByIdentity(string $identityId): array
    {
        $result = [];

        foreach ($this->links as $link) {
            if ($link->identityId === $identityId) {
                $result[] = $link;
            }
        }

        return $result;
    }

    public function save(AuthLink $link): AuthLink
    {
        if ($link->linkId === 0) {
            $saved = new AuthLink(
                linkId: $this->nextId++,
                identityId: $link->identityId,
                provider: $link->provider,
                externalId: $link->externalId,
                externalEmail: $link->externalEmail,
                externalDisplayName: $link->externalDisplayName,
                linkedAt: $link->linkedAt,
                lastUsedAt: $link->lastUsedAt,
                metadata: $link->metadata,
            );
            $this->links[] = $saved;
            return $saved;
        }

        foreach ($this->links as $i => $existing) {
            if ($existing->linkId === $link->linkId) {
                $this->links[$i] = $link;
                return $link;
            }
        }

        $this->links[] = $link;
        return $link;
    }

    public function delete(int $linkId): void
    {
        $this->links = array_values(array_filter(
            $this->links,
            fn(AuthLink $link): bool => $link->linkId !== $linkId
        ));
    }

    public function deleteByProviderAndExternalId(string $provider, string $externalId): void
    {
        $this->links = array_values(array_filter(
            $this->links,
            fn(AuthLink $link): bool => !($link->provider === $provider && $link->externalId === $externalId)
        ));
    }

    public function updateLastUsed(int $linkId, DateTimeImmutable $at): void
    {
        foreach ($this->links as $i => $link) {
            if ($link->linkId === $linkId) {
                $this->links[$i] = new AuthLink(
                    linkId: $link->linkId,
                    identityId: $link->identityId,
                    provider: $link->provider,
                    externalId: $link->externalId,
                    externalEmail: $link->externalEmail,
                    externalDisplayName: $link->externalDisplayName,
                    linkedAt: $link->linkedAt,
                    lastUsedAt: $at,
                    metadata: $link->metadata,
                );
                return;
            }
        }
    }
}
