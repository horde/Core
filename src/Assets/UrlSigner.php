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

namespace Horde\Core\Assets;

use Horde\Core\Factory\UrlSignerFactory;
use Horde\Injector\Attribute\Factory;

#[Factory(factory: UrlSignerFactory::class, method: 'create')]
interface UrlSigner
{
    /**
     * Sign a URL string by appending _t (timestamp) and _h (HMAC) params.
     */
    public function signUrl(string $url, ?int $now = null): string;

    /**
     * Verify a signed URL. Returns the original URL (without signature
     * params) on success, or false if verification fails.
     */
    public function verifySignedUrl(string $data, ?int $now = null): string|false;

    /**
     * Sign a query string by appending _t and _h params.
     */
    public function signQueryString(string $queryString, ?int $now = null): string;

    /**
     * Verify a signed query string.
     */
    public function verifySignedQueryString(string $data, ?int $now = null): bool;
}
