<?php

use Horde\Injector\Injector;

/**
 * @todo  Replace Horde_Core_Factory_Secret with this class.
 *
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_Secret_Cbc extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector|Injector $injector)
    {
        global $conf;

        // Configuration values are stable for the object's lifetime
        // and ride in via $params on construction. The per-request
        // HordeSession arrives later via setSession() because the
        // session doesn't exist yet at this point in the bootstrap.
        return new Horde_Core_Secret_Cbc([
            'cookie_domain' => $conf['cookie']['domain'],
            'cookie_path' => $conf['cookie']['path'],
            'cookie_ssl' => $conf['use_ssl'] == 1,
            'iv' => $conf['secret_key'],
            'session_name' => $conf['session']['name'],
            // HKDF inputs and the three-state format switch.
            'secret_key' => (string) ($conf['secret_key'] ?? ''),
            'key_format' => (string) (
                $conf['session']['key_format']
                ?? Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_WITH_LEGACY_FALLBACK
            ),
        ]);
    }
}
