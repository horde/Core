<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use Horde;
use Horde\Token\Token;
use Horde\Token\TokenConfig;
use Horde_Injector;
use Horde_String;
use Horde_Support_Randomid;

/**
 * Factory for the modern Horde\Token\Token CSRF service.
 *
 * Reads the same $conf['token'] settings as the legacy
 * Horde_Core_Factory_Token and produces a PSR-4 Token facade
 * backed by the configured storage driver (SQL, file, or null).
 *
 * The HMAC secret is sourced from the session, matching the
 * legacy factory behaviour so both old and new token services
 * generate compatible signatures within the same session.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class TokenServiceFactory
{
    public function create(Horde_Injector $injector): Token
    {
        global $conf, $session;

        // Determine driver from config (same source as legacy factory)
        $driver = empty($conf['token'])
            ? 'null'
            : Horde_String::lower($conf['token']['driver']);

        if ($driver === 'none') {
            $driver = 'null';
        }

        // Source the HMAC secret from the session (same as legacy factory)
        if (!$session->exists('horde', 'token_secret_key')) {
            $session->set(
                'horde',
                'token_secret_key',
                strval(new Horde_Support_Randomid())
            );
        }
        $secret = $session->get('horde', 'token_secret_key');

        // Driver-specific params from conf.php
        $params = empty($conf['token'])
            ? []
            : Horde::getDriverConfig('token', $conf['token']['driver']);

        // Token lifetime: conf value is in minutes, convert to seconds.
        // Default -1 = no expiry (matches TokenConfig default).
        $lifetimeSeconds = isset($conf['urls']['token_lifetime'])
            ? (int) $conf['urls']['token_lifetime'] * 60
            : -1;

        // Storage cleanup timeout (seconds), default 24 hours.
        $timeout = isset($params['timeout'])
            ? (int) $params['timeout']
            : 86400;

        $config = new TokenConfig(
            secret: $secret,
            tokenLifetime: $lifetimeSeconds,
            timeout: $timeout
        );

        return match ($driver) {
            'sql' => Token::sql(
                $secret,
                $injector->getInstance('Horde_Core_Factory_Db')
                    ->create('horde', 'token'),
                $config,
                $params['table'] ?? 'horde_tokens'
            ),
            'file' => Token::file(
                $secret,
                $params['token_dir'] ?? Horde::getTempDir(),
                $config
            ),
            default => Token::null($secret, $config),
        };
    }
}
