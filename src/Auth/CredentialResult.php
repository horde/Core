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

/**
 * Outcome of {@see AuthCredentialStore::getOrExplain()}.
 *
 * Pairs the credential array (when {@see state} is
 * {@see HasCredentialsState::Present}) with the state and metadata
 * needed to render an explanatory UI when credentials are absent.
 *
 * Designed for `match` over `state`:
 *
 * ```php
 * $result = $credStore->getOrExplain('imp');
 * match ($result->state) {
 *     HasCredentialsState::Present     => $imap->connect($user, $result->credentials),
 *     HasCredentialsState::NeverHad    => $this->redirectToAppLogin('imp', 'remember-me'),
 *     HasCredentialsState::Invalidated => $this->redirectToAppLogin('imp', $result->reason?->value ?? 'invalidated'),
 * };
 * ```
 *
 * `credentials` is non-null iff `state === Present`. The pair is enforced
 * at construction.
 */
final class CredentialResult
{
    /**
     * @param HasCredentialsState           $state       Current state.
     * @param array<string,mixed>|null      $credentials The credentials
     *                                                   array; non-null
     *                                                   iff state is
     *                                                   Present.
     * @param InvalidationReason|null       $reason      Reason for
     *                                                   invalidation;
     *                                                   meaningful only
     *                                                   when state is
     *                                                   Invalidated.
     * @param string|null                   $detail      Optional free-text
     *                                                   detail for log
     *                                                   trawling.
     */
    public function __construct(
        public readonly HasCredentialsState $state,
        public readonly ?array $credentials = null,
        public readonly ?InvalidationReason $reason = null,
        public readonly ?string $detail = null,
    ) {}
}
