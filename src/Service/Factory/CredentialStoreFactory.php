<?php

/**
 * Factory for CredentialStore.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service\Factory;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Service\CredentialStore;
use Horde\Core\Service\NullCredentialStore;
use Horde\Injector\Injector;
use Horde_Injector;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Resolves the configured password-credential store.
 *
 * Core owns the {@see CredentialStore} contract but ships no concrete
 * storage backend of its own (only {@see NullCredentialStore}). Real
 * backends live in downstream packages — e.g. base provides an
 * SQL-backed store. To avoid a Core -> base dependency, the concrete
 * backend is selected by configuration rather than wired in here.
 *
 * `password_credentials.storage_factory` holds the fully-qualified class
 * name of a daughter factory (any class exposing a
 * `create(Injector): CredentialStore` method). This factory loads that
 * class through the injector and delegates to it. When the key is empty,
 * names a missing class, or the delegate cannot produce a store, it falls
 * back to {@see NullCredentialStore} — warning first when a non-empty FQCN
 * was configured but could not be used, so misconfiguration is visible.
 */
class CredentialStoreFactory
{
    public function create(Horde_Injector|Injector $injector): CredentialStore
    {
        $hordeConfig = $injector->get(ConfigLoader::class)->load('horde');
        $factoryClass = (string) $hordeConfig->get(
            'password_credentials.storage_factory',
            '',
        );

        // Empty or explicitly disabled: No store and no warning.
        if ($factoryClass === '' || strtolower($factoryClass) === 'null') {
            return new NullCredentialStore();
        }

        // Guard against a config value that causes recursive recursion on this factory.
        if (ltrim($factoryClass, '\\') === self::class) {
            $this->logger($injector)->warning(sprintf(
                'Config Issue: password_credentials.storage_factory points at the Core '
                . 'resolver itself (%s). Falling back to NullCredentialStore.',
                $factoryClass,
            ));
            return new NullCredentialStore();
        }

        if (!class_exists($factoryClass)) {
            $this->logger($injector)->warning(sprintf(
                'Config Issue: Configured password credential store factory "%s" does not '
                . 'exist. Falling back to NullCredentialStore.',
                $factoryClass,
            ));
            return new NullCredentialStore();
        }

        try {
            $factory = $injector->get($factoryClass);
            $store = $factory->create($injector);
        } catch (Throwable $e) {
            print_r($e);
            exit;
            $this->logger($injector)->warning(sprintf(
                'Config Issue: Password credential store factory "%s" failed to produce a '
                . 'store (%s). Falling back to NullCredentialStore.',
                $factoryClass,
                $e->getMessage(),
            ));
            return new NullCredentialStore();
        }

        if (!$store instanceof CredentialStore) {
            $this->logger($injector)->warning(sprintf(
                'Config Issue: Password credential store factory "%s" returned %s, not a '
                . 'CredentialStore. Falling back to NullCredentialStore.',
                $factoryClass,
                get_debug_type($store),
            ));
            return new NullCredentialStore();
        }

        return $store;
    }

    private function logger(Horde_Injector|Injector $injector): LoggerInterface
    {
        try {
            return $injector->get(LoggerInterface::class);
        } catch (Throwable) {
            return new NullLogger();
        }
    }
}
