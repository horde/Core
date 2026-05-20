<?php

use Horde\Injector\Injector;
use NetDNS2\Resolver;
use NetDNS2\Exception as DnsException;

/**
 * @category Horde
 * @package  Core
 */
class Horde_Core_Factory_Dns extends Horde_Core_Factory_Injector
{
    public function create(Horde_Injector|Injector $injector)
    {
        if (!class_exists('NetDNS2\Resolver')) {
            return null;
        }

        if ($tmpdir = Horde::getTempDir()) {
            $config = [
                'cache_file' => $tmpdir . '/horde_dns.cache' . gethostname(),
                'cache_size' => 100000,
                'cache_type' => NetDNS2\Cache::CACHE_TYPE_FILE,
            ];
        } else {
            $config = [];
        }

        $resolver = new Resolver($config);

        if (is_readable('/etc/resolv.conf')) {
            try {
                $resolver->setServers('/etc/resolv.conf');
            } catch (DnsException $e) {
            }
        }

        return $resolver;
    }
}
