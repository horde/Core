<?php

use Horde\Core\Config\ConfigLoader;
use Horde\Injector\Injector;

/**
 * Factory for {@see Horde_Core_Secret_Cbc}.
 *
 * Reads config via the modern {@see ConfigLoader} rather than the legacy
 * `$conf` global. Modern PSR-15 route stacks that need session encryption
 * (through SessionLifecycle → SessionEncryptionCoordinator →
 * Horde_Secret_Cbc) resolve this factory before Horde_Registry::appInit()
 * has run, so `$conf` is not yet populated on those paths. ConfigLoader
 * reads from `var/config/horde/conf.php` directly.
 *
 * @todo  Replace Horde_Core_Factory_Secret with this class.
 *
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_Secret_Cbc extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector|Injector $injector)
    {
        $state = $injector->getInstance(ConfigLoader::class)->load('horde');

        // Configuration values are stable for the object's lifetime
        // and ride in via $params on construction. The per-request
        // HordeSession arrives later via setSession() because the
        // session doesn't exist yet at this point in the bootstrap.
        return new Horde_Core_Secret_Cbc([
            'cookie_domain' => (string) ($state->get('cookie.domain') ?? ''),
            'cookie_path'   => (string) ($state->get('cookie.path')   ?? ''),
            'cookie_ssl'    => ((int) ($state->get('use_ssl') ?? 0)) === 1,
            'iv'            => (string) ($state->get('secret_key') ?? ''),
            'session_name'  => (string) ($state->get('session.name') ?? ''),
            // HKDF inputs and the three-state format switch.
            'secret_key'    => (string) ($state->get('secret_key') ?? ''),
            'key_format'    => (string) (
                $state->get('session.key_format')
                ?? Horde_Core_Secret_Cbc::KEY_FORMAT_HKDF_WITH_LEGACY_FALLBACK
            ),
        ]);
    }
}
