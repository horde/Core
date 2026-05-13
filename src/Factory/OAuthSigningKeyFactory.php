<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
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

use Horde\Jwt\Key\PrivateKey;
use Horde\Jwt\Key\PublicKey;
use Horde\Jwt\Signer\Rs256Signer;
use Horde\Jwt\TokenEncoder;
use Horde\Horde\Service\CryptoKeyManager;
use Horde\Injector\Injector;
use Horde\Exception\HordeRuntimeException;

class OAuthSigningKeyFactory
{
    public function create(Injector $injector): PrivateKey
    {
        $conf = $GLOBALS['conf'] ?? [];
        $oauthConf = $conf['oauth_server'] ?? [];

        if (empty($oauthConf['enabled'])) {
            throw new HordeRuntimeException(
                'OAuth server is not enabled. Set $conf[\'oauth_server\'][\'enabled\'] = true in conf.php.'
            );
        }

        $keyFile = $oauthConf['private_key_file'] ?? '';
        if ($keyFile === '') {
            throw new HordeRuntimeException(
                'OAuth server private key not configured. Set $conf[\'oauth_server\'][\'private_key_file\'] in conf.php.'
            );
        }

        $keyManager = new CryptoKeyManager();
        $keyManager->ensureRsaKeyExists($keyFile);

        $privateKey = PrivateKey::fromFile($keyFile);

        $injector->setInstance(PublicKey::class, PublicKey::fromPrivateKey($privateKey));
        $injector->setInstance(Rs256Signer::class, new Rs256Signer($privateKey));
        $injector->setInstance(TokenEncoder::class, new TokenEncoder());

        return $privateKey;
    }
}
