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

namespace Horde\Core\Auth;

use DateTimeImmutable;

/**
 * Per-app credential state plus the metadata of its last transition.
 *
 * Returned by {@see AuthCredentialStore::getStateMetadata()}. The
 * timestamp is the moment the state was last set; the reason is
 * meaningful only when state is {@see HasCredentialsState::Invalidated}.
 */
final class CredentialStateMetadata
{
    public function __construct(
        public readonly HasCredentialsState $state,
        public readonly ?DateTimeImmutable $since = null,
        public readonly ?InvalidationReason $reason = null,
        public readonly ?string $detail = null,
    ) {}
}
