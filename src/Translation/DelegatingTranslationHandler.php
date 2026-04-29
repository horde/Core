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

/**
 * Concrete TranslationHandler that delegates to an underlying Handler.
 *
 * Created by TranslationManager for a specific domain+language pair.
 * Adds the _() convenience alias on top of the base Handler methods.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class DelegatingTranslationHandler implements TranslationHandler
{
    public function __construct(
        private readonly Handler $handler,
    ) {}

    public function t(string $message): string
    {
        return $this->handler->t($message);
    }

    public function ngettext(string $singular, string $plural, int $number): string
    {
        return $this->handler->ngettext($singular, $plural, $number);
    }

    public function format(string $message, array $params = [], ?string $locale = null): string
    {
        return $this->handler->format($message, $params, $locale);
    }

    public function _(string $message): string
    {
        return $this->handler->t($message);
    }
}
