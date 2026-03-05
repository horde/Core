<?php

declare(strict_types=1);

namespace Horde\Core\Auth\Jwt;

use InvalidArgumentException;

/**
 * Represents a private key for JWT signing
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class PrivateKey
{
    /**
     * @param string $content The PEM-encoded private key content
     */
    private function __construct(
        public readonly string $content
    ) {}

    /**
     * Create from PEM string content
     *
     * @param string $pemContent The PEM-encoded private key
     * @return self
     * @throws InvalidArgumentException If the key is invalid
     */
    public static function fromString(string $pemContent): self
    {
        if (trim($pemContent) === '') {
            throw new InvalidArgumentException('Private key content cannot be empty');
        }

        // Validate it's a valid private key by attempting to load it
        $resource = openssl_pkey_get_private($pemContent);
        if ($resource === false) {
            throw new InvalidArgumentException('Invalid private key format: ' . openssl_error_string());
        }

        return new self($pemContent);
    }

    /**
     * Create from PEM file path
     *
     * @param string $filePath Path to the PEM file
     * @return self
     * @throws InvalidArgumentException If file doesn't exist or key is invalid
     */
    public static function fromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Private key file not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new InvalidArgumentException("Private key file is not readable: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidArgumentException("Failed to read private key file: {$filePath}");
        }

        return self::fromString($content);
    }

    /**
     * Get the OpenSSL key resource
     *
     * @return resource OpenSSL key resource
     * @throws InvalidArgumentException If key cannot be loaded
     */
    public function getResource()
    {
        $resource = openssl_pkey_get_private($this->content);
        if ($resource === false) {
            throw new InvalidArgumentException('Failed to load private key: ' . openssl_error_string());
        }
        return $resource;
    }
}
