<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Translation;

use Horde\Translation\NullHandler;

/**
 * Injectable translation service for Horde applications.
 *
 * Manages translation domains (applications/libraries) and provides
 * configured TranslationHandler instances on request. Similar in role
 * to a database ConnectionManager: preloaded with connection configs,
 * returns ready-to-use handles.
 *
 * The caller determines both domain and language. TranslationManager
 * does not resolve language preferences — that is the responsibility
 * of the calling controller/service.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class TranslationManager
{
    /** @var array<string, DomainConfig> */
    private array $domains = [];

    /** @var array<string, TranslationHandler> Keyed by "domain:language" */
    private array $cache = [];

    /**
     * Register a translation domain.
     *
     * @param string $domain           The domain name (app name or library name).
     * @param string $localePath       Path to the locale directory.
     * @param callable|null $handlerFactory  Optional custom handler factory.
     *                                       Signature: fn(string $domain, string $localePath, string $language): Handler
     */
    public function addDomain(string $domain, string $localePath, ?callable $handlerFactory = null): void
    {
        $this->domains[$domain] = new DomainConfig($domain, $localePath, $handlerFactory);
    }

    /**
     * Get a TranslationHandler for a domain and language.
     *
     * Returns a cached instance if previously requested with the same
     * domain+language combination.
     *
     * @param string $domain    The translation domain.
     * @param string $language  The target language (e.g. 'de_DE', 'fr').
     *
     * @return TranslationHandler  A handler ready for t(), _(), format() calls.
     */
    public function handler(string $domain, string $language): TranslationHandler
    {
        $key = $domain . ':' . $language;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $config = $this->domains[$domain] ?? null;
        if ($config === null) {
            $this->cache[$key] = new DelegatingTranslationHandler(new NullHandler());
            return $this->cache[$key];
        }

        $handler = $config->createHandler($language);
        $this->cache[$key] = new DelegatingTranslationHandler($handler);
        return $this->cache[$key];
    }

    /**
     * Check whether a domain is registered.
     */
    public function hasDomain(string $domain): bool
    {
        return isset($this->domains[$domain]);
    }

    /**
     * List all registered domain names.
     *
     * @return string[]
     */
    public function listDomains(): array
    {
        return array_keys($this->domains);
    }
}
