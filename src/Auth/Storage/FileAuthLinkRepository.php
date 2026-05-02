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
use RuntimeException;

/**
 * File-based AuthLinkRepository for SQL-free deployments.
 *
 * Stores auth links as a JSON array in a single file with file locking
 * for safe concurrent access. Suitable for small deployments with a
 * handful of service users.
 */
class FileAuthLinkRepository implements AuthLinkRepository
{
    public function __construct(
        private readonly string $filePath,
    ) {}

    public function resolve(string $provider, string $externalId): ?AuthLink
    {
        $links = $this->readAll();

        foreach ($links as $data) {
            if ($data['provider'] === $provider && $data['external_id'] === $externalId) {
                return $this->hydrate($data);
            }
        }

        return null;
    }

    /** @return list<AuthLink> */
    public function findByIdentity(string $identityId): array
    {
        $links = $this->readAll();
        $result = [];

        foreach ($links as $data) {
            if ($data['identity_id'] === $identityId) {
                $result[] = $this->hydrate($data);
            }
        }

        return $result;
    }

    public function save(AuthLink $link): AuthLink
    {
        $links = $this->readAll();

        $data = $this->dehydrate($link);

        if ($link->linkId === 0) {
            $maxId = 0;
            foreach ($links as $existing) {
                if ($existing['link_id'] > $maxId) {
                    $maxId = $existing['link_id'];
                }
            }
            $data['link_id'] = $maxId + 1;
            $links[] = $data;
        } else {
            $found = false;
            foreach ($links as $i => $existing) {
                if ($existing['link_id'] === $link->linkId) {
                    $links[$i] = $data;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $links[] = $data;
            }
        }

        $this->writeAll($links);

        return $this->hydrate($data);
    }

    public function delete(int $linkId): void
    {
        $links = $this->readAll();
        $links = array_values(array_filter(
            $links,
            fn(array $data): bool => $data['link_id'] !== $linkId
        ));
        $this->writeAll($links);
    }

    public function deleteByProviderAndExternalId(string $provider, string $externalId): void
    {
        $links = $this->readAll();
        $links = array_values(array_filter(
            $links,
            fn(array $data): bool => !($data['provider'] === $provider && $data['external_id'] === $externalId)
        ));
        $this->writeAll($links);
    }

    public function updateLastUsed(int $linkId, DateTimeImmutable $at): void
    {
        $links = $this->readAll();

        foreach ($links as $i => $data) {
            if ($data['link_id'] === $linkId) {
                $links[$i]['last_used_at'] = $at->getTimestamp();
                $this->writeAll($links);
                return;
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function readAll(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $handle = fopen($this->filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Cannot open auth link file: ' . $this->filePath);
        }

        flock($handle, LOCK_SH);
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        if ($content === '' || $content === false) {
            return [];
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /** @param list<array<string, mixed>> $links */
    private function writeAll(array $links): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0o750, true);
        }

        $handle = fopen($this->filePath, 'c');
        if ($handle === false) {
            throw new RuntimeException('Cannot open auth link file for writing: ' . $this->filePath);
        }

        flock($handle, LOCK_EX);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode(array_values($links), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    private function hydrate(array $data): AuthLink
    {
        return new AuthLink(
            linkId: $data['link_id'],
            identityId: $data['identity_id'],
            provider: $data['provider'],
            externalId: $data['external_id'],
            externalEmail: $data['external_email'] ?? null,
            externalDisplayName: $data['external_display_name'] ?? null,
            linkedAt: new DateTimeImmutable('@' . $data['linked_at']),
            lastUsedAt: isset($data['last_used_at']) ? new DateTimeImmutable('@' . $data['last_used_at']) : null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    private function dehydrate(AuthLink $link): array
    {
        return [
            'link_id' => $link->linkId,
            'identity_id' => $link->identityId,
            'provider' => $link->provider,
            'external_id' => $link->externalId,
            'external_email' => $link->externalEmail,
            'external_display_name' => $link->externalDisplayName,
            'linked_at' => $link->linkedAt->getTimestamp(),
            'last_used_at' => $link->lastUsedAt?->getTimestamp(),
            'metadata' => $link->metadata,
        ];
    }
}
