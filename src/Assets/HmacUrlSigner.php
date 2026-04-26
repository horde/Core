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

class HmacUrlSigner implements UrlSigner
{
    public function __construct(
        private readonly string $secretKey,
        private readonly int $lifetimeMinutes = 30,
    ) {}

    public function signUrl(string $url, ?int $now = null): string
    {
        if ($url === '') {
            return $url;
        }

        $now ??= time();

        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . '_t=' . $now . '&_h=';
        $url .= self::uriB64Encode(
            hash_hmac('sha1', $url, $this->secretKey, true)
        );

        return $url;
    }

    public function verifySignedUrl(string $data, ?int $now = null): string|false
    {
        $now ??= time();

        $pos = strrpos($data, '&_h=');
        if ($pos === false) {
            return false;
        }
        $pos += 4;

        $url = substr($data, 0, $pos);
        $hmac = substr($data, $pos);

        if ($hmac !== self::uriB64Encode(hash_hmac('sha1', $url, $this->secretKey, true))) {
            return false;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $values);
        if (!isset($values['_t'])) {
            return false;
        }
        if ((int) $values['_t'] + $this->lifetimeMinutes * 60 < $now) {
            return false;
        }

        $pos = strrpos($data, '&_t=');
        if ($pos === false) {
            $pos = strrpos($data, '?_t=');
        }
        if ($pos === false) {
            return false;
        }

        return substr($data, 0, $pos);
    }

    public function signQueryString(string $queryString, ?int $now = null): string
    {
        $now ??= time();

        $queryString .= '&_t=' . $now . '&_h=';

        return $queryString . self::uriB64Encode(
            hash_hmac('sha1', $queryString, $this->secretKey, true)
        );
    }

    public function verifySignedQueryString(string $data, ?int $now = null): bool
    {
        $now ??= time();

        $pos = strrpos($data, '&_h=');
        if ($pos === false) {
            return false;
        }
        $pos += 4;

        $queryString = substr($data, 0, $pos);
        $hmac = substr($data, $pos);

        if ($hmac !== self::uriB64Encode(hash_hmac('sha1', $queryString, $this->secretKey, true))) {
            return false;
        }

        parse_str($queryString, $values);
        if (!isset($values['_t'])) {
            return false;
        }

        return !((int) $values['_t'] + $this->lifetimeMinutes * 60 < $now);
    }

    private static function uriB64Encode(string $string): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
    }
}
