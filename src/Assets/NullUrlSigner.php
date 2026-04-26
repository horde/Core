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

class NullUrlSigner implements UrlSigner
{
    public function signUrl(string $url, ?int $now = null): string
    {
        return $url;
    }

    public function verifySignedUrl(string $data, ?int $now = null): string|false
    {
        return $data;
    }

    public function signQueryString(string $queryString, ?int $now = null): string
    {
        return $queryString;
    }

    public function verifySignedQueryString(string $data, ?int $now = null): bool
    {
        return true;
    }
}
