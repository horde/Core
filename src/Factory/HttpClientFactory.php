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
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Factory;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\Http\Client\Curl;
use Horde\Http\Client\Fopen;
use Horde\Http\Client\Options;
use Horde\Injector\Injector;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Factory for PSR-18 HTTP Client
 *
 * Modern mirror of Horde_Core_Factory_HttpClient. Uses ConfigLoader
 * instead of global $conf and returns a PSR-18 ClientInterface
 * (Curl when available, Fopen otherwise).
 *
 * Reads proxy configuration from the horde config tree at http.proxy.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class HttpClientFactory
{
    /**
     * Create a PSR-18 HTTP client
     */
    public function create(Injector $injector): ClientInterface
    {
        $state = $this->loadConfig($injector);
        $options = $this->buildOptions($state);
        $responseFactory = $injector->getInstance(ResponseFactoryInterface::class);
        $streamFactory = $injector->getInstance(StreamFactoryInterface::class);

        if (extension_loaded('curl')) {
            return new Curl($responseFactory, $streamFactory, $options);
        }

        return new Fopen($responseFactory, $streamFactory, $options);
    }

    private function loadConfig(Injector $injector): State
    {
        $loader = $injector->getInstance(ConfigLoader::class);

        return $loader->load('horde');
    }

    private function buildOptions(State $state): Options
    {
        $opts = [];

        $proxyHost = $state->get('http.proxy.proxy_host');
        if ($proxyHost !== null && $proxyHost !== '') {
            $opts['proxyServer'] = (string) $proxyHost;

            $proxyPort = $state->get('http.proxy.proxy_port');
            if ($proxyPort !== null) {
                $opts['proxyPort'] = (int) $proxyPort;
            }

            $proxyUser = $state->get('http.proxy.proxy_user');
            if ($proxyUser !== null && $proxyUser !== '') {
                $opts['proxyUsername'] = (string) $proxyUser;

                $proxyPass = $state->get('http.proxy.proxy_pass');
                if ($proxyPass !== null && $proxyPass !== '') {
                    $opts['proxyPassword'] = (string) $proxyPass;
                }
            }
        }

        return new Options($opts);
    }
}
