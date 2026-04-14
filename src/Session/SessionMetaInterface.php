<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */

namespace Horde\Core\Session;

use DateTimeImmutable;

/**
 * Read projections over well-known Horde authentication keys.
 *
 * Session objects implementing this interface expose structured metadata
 * extracted from the session payload — authenticated user, browser
 * fingerprint, remote address, timestamps, and authenticated applications.
 *
 * This enables the admin "active sessions" page to display session details
 * without parsing raw payload data. Backends do not implement this —
 * session objects do. An admin controller would load a session, check
 * `instanceof SessionMetaInterface`, and call its accessors.
 */
interface SessionMetaInterface
{
    /**
     * Get the authenticated user ID.
     */
    public function getAuthenticatedUser(): ?string;

    /**
     * Get the original authentication ID (login name before any mapping).
     */
    public function getAuthId(): ?string;

    /**
     * Get the browser fingerprint string stored at authentication time.
     */
    public function getBrowserFingerprint(): ?string;

    /**
     * Get the remote address stored at authentication time.
     */
    public function getRemoteAddress(): ?string;

    /**
     * Get the timestamp of the authentication event.
     */
    public function getAuthTimestamp(): ?DateTimeImmutable;

    /**
     * Get the timestamp when the session was first created.
     */
    public function getSessionBegin(): ?DateTimeImmutable;

    /**
     * Get the list of application names that have stored credentials.
     *
     * @return array<string> Application names (e.g. 'imp', 'kronolith')
     */
    public function getAuthenticatedApps(): array;
}
