<?php
/**
 * Password-based credential value object.
 *
 * Represents retrieved credentials for password-based authentication systems.
 * Supports both structured (username/password) and opaque (bearer token) formats.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

interface PasswordCredential
{
    public function credentialId(): string;
    public function userId(): string;
    public function providerId(): string;
    public function purpose(): ServicePurpose;

    /**
     * Returns credential as structured array.
     * Typically ['username' => '...', 'password' => '...']
     */
    public function asStructured(): array;

    /**
     * Returns credential as opaque string.
     * For bearer tokens, API keys, or encoded strings.
     */
    public function asOpaque(): string;

    /**
     * Convenience method for HTTP Bearer header value.
     */
    public function asBearerToken(): string;

    /**
     * Convenience method for HTTP Basic Auth header value.
     * Returns base64-encoded "username:password"
     */
    public function asBasicAuth(): string;

    public function createdAt(): int;
    public function updatedAt(): int;
}
