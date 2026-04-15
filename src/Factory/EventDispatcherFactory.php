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

use Horde\EventDispatcher\EventDispatcher;
use Horde\EventDispatcher\SimpleListenerProvider;
use Horde\Injector\Injector;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Factory for PSR-14 EventDispatcher and ListenerProvider
 *
 * Provides both the EventDispatcher and the shared ListenerProvider
 * as separate factory methods so consumers that only need the provider
 * get the same instance used by the dispatcher.
 *
 * @category Horde
 * @package  Core
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class EventDispatcherFactory
{
    private ?SimpleListenerProvider $provider = null;

    /**
     * Create or return the shared ListenerProvider
     */
    public function createListenerProvider(Injector $injector): SimpleListenerProvider
    {
        if ($this->provider === null) {
            $this->provider = new SimpleListenerProvider();
        }

        return $this->provider;
    }

    /**
     * Create the EventDispatcher with shared ListenerProvider
     */
    public function create(Injector $injector): EventDispatcher
    {
        $provider = $this->createListenerProvider($injector);

        try {
            $logger = $injector->getInstance(LoggerInterface::class);
        } catch (\Throwable) {
            $logger = new NullLogger();
        }

        return new EventDispatcher($provider, $logger);
    }
}
