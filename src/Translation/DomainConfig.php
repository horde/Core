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

use Horde\Translation\Handler;
use Horde\Translation\GettextHandler;

/**
 * Per-domain configuration for TranslationManager.
 *
 * Holds the locale path and an optional handler factory for a single
 * translation domain. When createHandler() is called with a language,
 * it produces the configured Handler instance.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class DomainConfig
{
    /** @var callable|null fn(string $domain, string $localePath, string $language): Handler */
    private $handlerFactory;

    /**
     * @param string $domain           The translation domain name.
     * @param string $localePath       Path to the locale directory.
     * @param callable|null $handlerFactory  Custom handler factory. Receives
     *                                       (string $domain, string $localePath, string $language)
     *                                       and returns a Handler instance.
     */
    public function __construct(
        private readonly string $domain,
        private readonly string $localePath,
        ?callable $handlerFactory = null,
    ) {
        $this->handlerFactory = $handlerFactory;
    }

    /**
     * Create a Handler for this domain in the given language.
     *
     * @param string $language  The target language (e.g. 'de_DE', 'fr').
     *
     * @return Handler  A configured translation handler.
     */
    public function createHandler(string $language): Handler
    {
        if ($this->handlerFactory !== null) {
            return ($this->handlerFactory)($this->domain, $this->localePath, $language);
        }

        return new GettextHandler($this->domain, $this->localePath);
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getLocalePath(): string
    {
        return $this->localePath;
    }
}
